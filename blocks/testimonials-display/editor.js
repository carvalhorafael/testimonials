( function ( blocks, blockEditor, components, coreData, data, element, i18n, serverSideRender ) {
	var registerBlockType = blocks.registerBlockType;
	var registerBlockVariation = blocks.registerBlockVariation;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var FormTokenField = components.FormTokenField;
	var useSelect = data.useSelect;
	var store = coreData.store;
	var createElement = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;

	var POST_TYPE = 'depoimento';
	var TAXONOMY = 'depoimento_categoria';
	var BLOCK_NAME = 'testimonials/testimonials-display';

	var layoutOptions = [
		{ label: __( 'Cards', 'testimonials' ), value: 'cards' },
		{ label: __( 'Grid', 'testimonials' ), value: 'grid' },
		{ label: __( 'Slider', 'testimonials' ), value: 'slider' },
		{ label: __( 'Video slider', 'testimonials' ), value: 'video-slider' },
		{ label: __( 'Featured', 'testimonials' ), value: 'featured' }
	];

	var selectionOptions = [
		{ label: __( 'Latest', 'testimonials' ), value: 'latest' },
		{ label: __( 'Manual', 'testimonials' ), value: 'manual' },
		{ label: __( 'Category', 'testimonials' ), value: 'category' }
	];

	var orderOptions = [
		{ label: __( 'Newest first', 'testimonials' ), value: 'desc' },
		{ label: __( 'Oldest first', 'testimonials' ), value: 'asc' }
	];

	var orderbyOptions = [
		{ label: __( 'Date', 'testimonials' ), value: 'date' },
		{ label: __( 'Menu order', 'testimonials' ), value: 'menu_order' },
		{ label: __( 'Random', 'testimonials' ), value: 'rand' }
	];

	function stripHTML( value ) {
		var elementNode = document.createElement( 'div' );
		elementNode.innerHTML = value || '';
		return elementNode.textContent || elementNode.innerText || '';
	}

	function getPostLabel( post ) {
		return stripHTML( post && post.title ? post.title.rendered : '' ) || __( '(no title)', 'testimonials' );
	}

	function idsToLabels( ids, records, getLabel ) {
		if ( ! Array.isArray( ids ) || ! Array.isArray( records ) ) {
			return [];
		}

		return ids.map( function ( id ) {
			var match = records.find( function ( record ) {
				return record.id === id;
			} );

			return match ? getLabel( match ) : String( id );
		} );
	}

	function labelsToIds( labels, records, getLabel ) {
		if ( ! Array.isArray( labels ) || ! Array.isArray( records ) ) {
			return [];
		}

		return labels.reduce( function ( ids, label ) {
			var match = records.find( function ( record ) {
				return getLabel( record ) === label || String( record.id ) === label;
			} );

			if ( match ) {
				ids.push( match.id );
			}

			return ids;
		}, [] );
	}

	function Edit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps( {
			className: 'testimonials-block testimonials-block--' + ( attributes.layout || 'cards' )
		} );

		var records = useSelect( function ( select ) {
			return {
				testimonials: select( store ).getEntityRecords( 'postType', POST_TYPE, {
					per_page: 100,
					status: 'publish',
					orderby: 'date',
					order: 'desc',
					_fields: 'id,title'
				} ),
				categories: select( store ).getEntityRecords( 'taxonomy', TAXONOMY, {
					per_page: 100,
					_fields: 'id,name'
				} )
			};
		}, [] );

		var testimonials = records.testimonials || [];
		var categories = records.categories || [];
		var testimonialLabels = idsToLabels( attributes.testimonialIds, testimonials, getPostLabel );
		var categoryLabels = idsToLabels( attributes.categoryIds, categories, function ( term ) {
			return term.name;
		} );

		return createElement(
			'div',
			blockProps,
			createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: __( 'Display', 'testimonials' ), initialOpen: true },
					createElement( SelectControl, {
						label: __( 'Layout', 'testimonials' ),
						value: attributes.layout,
						options: layoutOptions,
						onChange: function ( layout ) {
							setAttributes( {
								layout: layout,
								showVideo: 'video-slider' === layout ? true : attributes.showVideo,
								count: 'featured' === layout ? 1 : attributes.count
							} );
						}
					} ),
					createElement( SelectControl, {
						label: __( 'Selection mode', 'testimonials' ),
						value: attributes.selectionMode,
						options: selectionOptions,
						onChange: function ( selectionMode ) {
							setAttributes( { selectionMode: selectionMode } );
						}
					} ),
					createElement( RangeControl, {
						label: __( 'Quantity', 'testimonials' ),
						value: attributes.count,
						min: 1,
						max: 12,
						onChange: function ( count ) {
							setAttributes( { count: count || 1 } );
						}
					} ),
					createElement( SelectControl, {
						label: __( 'Order by', 'testimonials' ),
						value: attributes.orderby,
						options: orderbyOptions,
						onChange: function ( orderby ) {
							setAttributes( { orderby: orderby } );
						}
					} ),
					createElement( SelectControl, {
						label: __( 'Order', 'testimonials' ),
						value: attributes.order,
						options: orderOptions,
						onChange: function ( order ) {
							setAttributes( { order: order } );
						}
					} )
				),
				'category' === attributes.selectionMode &&
					createElement(
						PanelBody,
						{ title: __( 'Categories', 'testimonials' ), initialOpen: true },
						createElement( FormTokenField, {
							label: __( 'Categories', 'testimonials' ),
							value: categoryLabels,
							suggestions: categories.map( function ( term ) {
								return term.name;
							} ),
							onChange: function ( labels ) {
								setAttributes( {
									categoryIds: labelsToIds( labels, categories, function ( term ) {
										return term.name;
									} )
								} );
							}
						} )
					),
				'manual' === attributes.selectionMode &&
					createElement(
						PanelBody,
						{ title: __( 'Testimonials', 'testimonials' ), initialOpen: true },
						createElement( FormTokenField, {
							label: __( 'Testimonials', 'testimonials' ),
							value: testimonialLabels,
							suggestions: testimonials.map( getPostLabel ),
							onChange: function ( labels ) {
								setAttributes( {
									testimonialIds: labelsToIds( labels, testimonials, getPostLabel )
								} );
							}
						} )
					),
				createElement(
					PanelBody,
					{ title: __( 'Content', 'testimonials' ), initialOpen: false },
					createElement( ToggleControl, {
						label: __( 'Show video', 'testimonials' ),
						checked: attributes.showVideo,
						onChange: function ( showVideo ) {
							setAttributes( { showVideo: showVideo } );
						}
					} ),
					createElement( ToggleControl, {
						label: __( 'Show excerpt', 'testimonials' ),
						checked: attributes.showExcerpt,
						onChange: function ( showExcerpt ) {
							setAttributes( { showExcerpt: showExcerpt } );
						}
					} ),
					createElement( ToggleControl, {
						label: __( 'Show category', 'testimonials' ),
						checked: attributes.showCategory,
						onChange: function ( showCategory ) {
							setAttributes( { showCategory: showCategory } );
						}
					} )
				)
			),
			createElement( ServerSideRender, {
				block: BLOCK_NAME,
				attributes: attributes
			} )
		);
	}

	registerBlockType( BLOCK_NAME, {
		apiVersion: 3,
		title: __( 'Depoimentos', 'testimonials' ),
		description: __( 'Exibe depoimentos publicados com opções de seleção, quantidade, categoria e layout.', 'testimonials' ),
		category: 'widgets',
		icon: 'format-quote',
		attributes: {
			layout: {
				type: 'string',
				default: 'cards'
			},
			selectionMode: {
				type: 'string',
				default: 'latest'
			},
			count: {
				type: 'number',
				default: 3
			},
			testimonialIds: {
				type: 'array',
				default: []
			},
			categoryIds: {
				type: 'array',
				default: []
			},
			order: {
				type: 'string',
				default: 'desc'
			},
			orderby: {
				type: 'string',
				default: 'date'
			},
			showVideo: {
				type: 'boolean',
				default: false
			},
			showExcerpt: {
				type: 'boolean',
				default: true
			},
			showCategory: {
				type: 'boolean',
				default: true
			}
		},
		supports: {
			customClassName: true,
			html: false
		},
		edit: Edit,
		save: function () {
			return null;
		}
	} );

	registerBlockVariation( BLOCK_NAME, {
		name: 'cards',
		title: __( 'Depoimentos em cards', 'testimonials' ),
		scope: [ 'inserter' ],
		attributes: { layout: 'cards' }
	} );

	registerBlockVariation( BLOCK_NAME, {
		name: 'grid',
		title: __( 'Grade de depoimentos', 'testimonials' ),
		scope: [ 'inserter' ],
		attributes: { layout: 'grid' }
	} );

	registerBlockVariation( BLOCK_NAME, {
		name: 'slider',
		title: __( 'Slider de depoimentos', 'testimonials' ),
		scope: [ 'inserter' ],
		attributes: { layout: 'slider' }
	} );

	registerBlockVariation( BLOCK_NAME, {
		name: 'video-slider',
		title: __( 'Slider com vídeos', 'testimonials' ),
		scope: [ 'inserter' ],
		attributes: { layout: 'video-slider', showVideo: true }
	} );

	registerBlockVariation( BLOCK_NAME, {
		name: 'featured',
		title: __( 'Depoimento em destaque', 'testimonials' ),
		scope: [ 'inserter' ],
		attributes: { layout: 'featured', count: 1 }
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.coreData,
	window.wp.data,
	window.wp.element,
	window.wp.i18n,
	window.wp.serverSideRender
);
