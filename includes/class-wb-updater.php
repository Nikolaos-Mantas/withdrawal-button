<?php
/**
 * GitHub Releases updater with stable / beta / alpha channels.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WB_Updater {

	const CACHE_KEY = 'wb_github_releases';

	/**
	 * Initialize update hooks.
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_action( 'in_plugin_update_message-' . plugin_basename( WB_FILE ), array( __CLASS__, 'update_message' ), 10, 2 );
	}

	/**
	 * Get configured update channel.
	 *
	 * @return string stable|beta|alpha
	 */
	public static function get_channel() {
		$channel = WB_Settings::get()['update_channel'];
		return in_array( $channel, array( 'stable', 'beta', 'alpha' ), true ) ? $channel : 'stable';
	}

	/**
	 * Detect release tier from tag name.
	 *
	 * @param string $tag Tag name.
	 * @return string stable|beta|alpha
	 */
	public static function release_tier( $tag ) {
		$tag = ltrim( (string) $tag, 'v' );

		if ( false !== stripos( $tag, '-alpha' ) ) {
			return 'alpha';
		}

		if ( false !== stripos( $tag, '-beta' ) ) {
			return 'beta';
		}

		return 'stable';
	}

	/**
	 * Normalize tag for version_compare (strip leading v).
	 *
	 * @param string $tag Tag name.
	 * @return string
	 */
	public static function normalize_version( $tag ) {
		return ltrim( (string) $tag, 'v' );
	}

	/**
	 * Whether a release matches the requested channel.
	 *
	 * @param array<string, mixed> $release GitHub release object.
	 * @param string               $channel Channel name.
	 * @return bool
	 */
	public static function release_matches_channel( $release, $channel ) {
		$tag = isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '';
		if ( '' === $tag ) {
			return false;
		}

		$tier = self::release_tier( $tag );

		if ( 'stable' === $channel ) {
			return 'stable' === $tier && empty( $release['prerelease'] );
		}

		return $tier === $channel;
	}

	/**
	 * Pick the newest release for a channel from API response.
	 *
	 * @param array<int, array<string, mixed>> $releases Releases list.
	 * @param string                            $channel  Channel.
	 * @return array<string, mixed>|null
	 */
	public static function pick_release_for_channel( $releases, $channel ) {
		$best = null;
		$best_version = '';

		foreach ( $releases as $release ) {
			if ( ! self::release_matches_channel( $release, $channel ) ) {
				continue;
			}

			$version = self::normalize_version( $release['tag_name'] ?? '' );
			if ( '' === $version ) {
				continue;
			}

			if ( null === $best || version_compare( $version, $best_version, '>' ) ) {
				$best         = $release;
				$best_version = $version;
			}
		}

		return $best;
	}

	/**
	 * Find plugin zip download URL on a release.
	 *
	 * @param array<string, mixed> $release Release object.
	 * @return string
	 */
	public static function get_zip_download_url( $release ) {
		if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return '';
		}

		foreach ( $release['assets'] as $asset ) {
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			if ( $name === 'withdrawal-button.zip' && ! empty( $asset['browser_download_url'] ) ) {
				return (string) $asset['browser_download_url'];
			}
		}

		return '';
	}

	/**
	 * Fetch releases from GitHub (cached).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_releases() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = 'https://api.github.com/repos/' . WB_GITHUB_REPO . '/releases';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Withdrawal-Button/' . WB_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		set_site_transient( self::CACHE_KEY, $data, HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Get update info for the configured channel.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_remote_update() {
		$channel  = self::get_channel();
		$releases = self::get_releases();
		$release  = self::pick_release_for_channel( $releases, $channel );

		if ( null === $release ) {
			return null;
		}

		$version = self::normalize_version( $release['tag_name'] ?? '' );
		$package = self::get_zip_download_url( $release );

		if ( '' === $version || '' === $package ) {
			return null;
		}

		if ( version_compare( $version, WB_VERSION, '<=' ) ) {
			return null;
		}

		return array(
			'version'  => $version,
			'package'  => $package,
			'url'      => isset( $release['html_url'] ) ? (string) $release['html_url'] : '',
			'notes'    => isset( $release['body'] ) ? (string) $release['body'] : '',
			'tier'     => self::release_tier( $release['tag_name'] ?? '' ),
			'channel'  => $channel,
			'tag_name' => (string) $release['tag_name'],
		);
	}

	/**
	 * Inject update into WordPress plugins transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = self::get_remote_update();
		if ( null === $remote ) {
			return $transient;
		}

		$plugin_file = plugin_basename( WB_FILE );

		$transient->response[ $plugin_file ] = (object) array(
			'slug'        => WB_PLUGIN_SLUG,
			'plugin'      => $plugin_file,
			'new_version' => $remote['version'],
			'url'         => $remote['url'],
			'package'     => $remote['package'],
			'icons'       => array(),
			'banners'     => array(),
			'banners_rtl' => array(),
			'tested'      => '',
			'requires_php' => '7.4',
			'compatibility' => new stdClass(),
		);

		set_site_transient( 'wb_pending_update_tier', $remote['tier'], DAY_IN_SECONDS );

		return $transient;
	}

	/**
	 * Provide plugin information for the update details modal.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action Action.
	 * @param object             $args   Args.
	 * @return false|object
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || WB_PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$remote = self::get_remote_update();
		if ( null === $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Withdrawal Button',
			'slug'          => WB_PLUGIN_SLUG,
			'version'       => $remote['version'],
			'author'        => '<a href="' . esc_url( WB_AUTHOR_URI ) . '">' . esc_html( WB_AUTHOR ) . '</a>',
			'homepage'      => WB_AUTHOR_URI,
			'download_link' => $remote['package'],
			'sections'      => array(
				'description' => __( 'Withdrawal request form for WordPress.', WB_TEXT_DOMAIN ),
				'changelog'   => $remote['notes'] ?: __( 'No changelog provided for this release.', WB_TEXT_DOMAIN ),
			),
		);
	}

	/**
	 * Show pre-release warning on the Plugins screen.
	 *
	 * @param array<string, mixed> $plugin_data Plugin data.
	 * @param object               $response    Update response.
	 */
	public static function update_message( $plugin_data, $response ) {
		$tier = get_site_transient( 'wb_pending_update_tier' );

		if ( ! $tier ) {
			$tier = self::release_tier( $response->new_version ?? '' );
		}

		if ( 'beta' === $tier || 'alpha' === $tier ) {
			echo '<br><strong>' . esc_html__( 'Pre-release update:', WB_TEXT_DOMAIN ) . '</strong> ';
			echo esc_html__( 'This is a beta/alpha build. Back up your site before upgrading.', WB_TEXT_DOMAIN );
		}

		$channel = self::get_channel();
		if ( 'beta' === $channel || 'alpha' === $channel ) {
			echo '<br><em>' . esc_html__( 'Your update channel is set to a pre-release track in Withdrawal Settings → Updates.', WB_TEXT_DOMAIN ) . '</em>';
		}
	}
}
