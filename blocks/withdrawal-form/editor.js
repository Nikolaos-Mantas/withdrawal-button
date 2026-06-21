( function ( blocks, blockEditor, i18n ) {
	var el = blocks.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'wb/withdrawal-form', {
		edit: function () {
			var blockProps = useBlockProps( {
				className: 'wb-block-placeholder',
			} );

			return el(
				'div',
				blockProps,
				el( 'p', { style: { padding: '20px', border: '1px dashed #ccc', textAlign: 'center' } },
					i18n.__( 'Withdrawal Form — preview on the front end.', 'withdrawal-button' )
				)
			);
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.i18n );
