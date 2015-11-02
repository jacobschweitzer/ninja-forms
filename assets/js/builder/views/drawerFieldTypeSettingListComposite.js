define( ['builder/views/drawerFieldTypeSettingListOption', 'builder/views/drawerFieldTypeSettingListEmpty'], function( listOptionView, listEmptyView ) {
	var view = Marionette.CompositeView.extend( {
		template: '#nf-tmpl-edit-field-setting-wrap',
		childView: listOptionView,
		emptyView: listEmptyView,

		initialize: function( data ) {
			this.collection = data.fieldModel.get( 'options' );
			this.fieldModel = data.fieldModel;
			this.childViewOptions = { collection: this.collection, fieldModel: data.fieldModel };
		},

		onRender: function() {
			this.$el = this.$el.children();
			this.$el.unwrap();
			this.setElement( this.$el );

			this.$el = this.$el.children();
			this.$el.unwrap();
			this.setElement( this.$el );
		
			var that = this;
			jQuery( this.el ).find( '.nf-list-options-tbody' ).sortable( {
				handle: '.handle',
				helper: 'clone',
				placeholder: 'nf-list-options-sortable-placeholder',
				forcePlaceholderSize: true,
				opacity: 0.95,
				tolerance: 'pointer',

				start: function( e, ui ) {
					nfRadio.channel( 'list-repeater' ).request( 'start:optionSortable', ui );
				},

				stop: function( e, ui ) {
					nfRadio.channel( 'list-repeater' ).request( 'stop:optionSortable', ui );
				},

				update: function( e, ui ) {
					nfRadio.channel( 'list-repeater' ).request( 'update:optionSortable', this, that );
				}
			} );
		},

		templateHelpers: function () {
	    	return {
	    		renderSetting: function(){
					return _.template( jQuery( '#nf-tmpl-edit-field-setting-' + this.type ).html(), this );
				},

			};
		},

		attachHtml: function( collectionView, childView ) {
			jQuery( collectionView.el ).find( '.nf-list-options-tbody' ).append( childView.el );
		},

		events: {
			'click .nf-add-new': 'addOption'
		},

		addOption: function( e ) {
			nfRadio.channel( 'list-repeater' ).request( 'add:option', this.collection, this.fieldModel );
		}

	} );

	return view;
} );