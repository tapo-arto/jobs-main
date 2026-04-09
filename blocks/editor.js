( function ( blocks, element, blockEditor, components ) {
    var el = element.createElement;
    var __ = wp.i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;

    blocks.registerBlockType( 'tjobs/jobs-list', {
        title: __( 'Jobs List', 'tapojarvijobs' ),
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            itemsCount: { type: 'number', default: 10 },
            showSearch: { type: 'boolean', default: false },
            layout: { type: 'string', default: 'list' },
        },
        edit: function ( props ) {
            var attributes = props.attributes;
            return [
                el( InspectorControls, { key: 'inspector' },
                    el( PanelBody, { title: __( 'Asetukset', 'tapojarvijobs' ), initialOpen: true },
                        el( TextControl, {
                            label: __( 'Näytettävien töiden määrä', 'tapojarvijobs' ),
                            type: 'number',
                            value: attributes.itemsCount,
                            onChange: function ( val ) { props.setAttributes( { itemsCount: parseInt( val, 10 ) } ); },
                        } ),
                        el( ToggleControl, {
                            label: __( 'Näytä hakukenttä', 'tapojarvijobs' ),
                            checked: attributes.showSearch,
                            onChange: function ( val ) { props.setAttributes( { showSearch: val } ); },
                        } ),
                        el( SelectControl, {
                            label: __( 'Asettelu', 'tapojarvijobs' ),
                            value: attributes.layout,
                            options: [
                                { label: __( 'Lista', 'tapojarvijobs' ), value: 'list' },
                                { label: __( 'Ruudukko', 'tapojarvijobs' ), value: 'grid' },
                                { label: __( 'Kortti', 'tapojarvijobs' ), value: 'card' },
                            ],
                            onChange: function ( val ) { props.setAttributes( { layout: val } ); },
                        } )
                    )
                ),
                el( 'div', { key: 'preview', className: 'tjobs-block-preview' },
                    el( 'p', {}, __( 'Avoimet työpaikat – esikatselu', 'tapojarvijobs' ) )
                ),
            ];
        },
        save: function () {
            // Palvelinpuolen renderöinti (render_callback)
            return null;
        },
    } );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components ) );
