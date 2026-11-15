/* Botón Claro blocks in the editor: server-rendered, with a type picker for the form. No build step. */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks || !wp.element) { return; }
	var el = wp.element.createElement, __ = wp.i18n.__;
	var SSR = wp.serverSideRender, be = wp.blockEditor, C = wp.components;
	function edit(name, picker) {
		return function (props) {
			var bp = be.useBlockProps ? be.useBlockProps() : {};
			var out = [el(SSR, { key: 'r', block: name, attributes: props.attributes })];
			if (picker) {
				out.unshift(el(be.InspectorControls, { key: 'i' }, el(C.PanelBody, { title: __('Formulario', 'boton-de-arrepentimiento') },
					el(C.SelectControl, {
						label: __('Tipo', 'boton-de-arrepentimiento'),
						value: props.attributes.tipo,
						options: [
							{ label: __('Arrepentimiento', 'boton-de-arrepentimiento'), value: 'arrepentimiento' },
							{ label: __('Baja de servicio', 'boton-de-arrepentimiento'), value: 'baja' }
						],
						onChange: function (v) { props.setAttributes({ tipo: v }); }
					}))));
			}
			return el('div', bp, out);
		};
	}
	['boton-claro/botones', 'boton-claro/formulario', 'boton-claro/atencion'].forEach(function (n) {
		wp.blocks.registerBlockType(n, { edit: edit(n, n === 'boton-claro/formulario'), save: function () { return null; } });
	});
}(window.wp));
