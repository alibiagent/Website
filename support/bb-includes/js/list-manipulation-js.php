<?php @require_once('../../bb-load.php'); cache_javascript_headers(); ?>
addLoadEvent(function(){theList=new listMan();});
function deleteSomething(what,id,message,obj){if(!obj)obj=theList;if(!message)message="<?php printf(__('Are you sure you want to delete this %s?'),"'+what+'"); ?>";if(confirm(message))return obj.ajaxDelete(what,id);else return false;}
function dimSomething(what,id,dimClass,obj){if(!obj)obj=theList;return obj.ajaxDimmer(what,id,dimClass);}

var listMan = Class.create();
Object.extend(listMan.prototype, {
	ajaxRespEl: 'ajax-response',
	ajaxHandler: false,
	inputData: '',
	clearInputs: [],
	showLink: true,
	topAdder: false,
	alt: 'alternate',
	altOffset: 0,
	addComplete: null,
	delComplete: null,
	dimComplete: null,
	dataStore: null,
	formStore: null,

	initialize: function(theListId) {
		this.theList = $(theListId ? theListId : 'the-list');
		if ( !this.theList )
			return false;
		this.theList.cleanWhitespace();
	},

	// sends add-what and fields contained in where
	// recieves html with top element having an id like what-#
	ajaxAdder: function( what, where, update ) { // Do NOT wrap TR in TABLE TBODY
		var ajaxAdd = new WPAjax( this.ajaxHandler, this.ajaxRespEl );
		if ( ajaxAdd.notInitialized() )
			return true;
		ajaxAdd.options.parameters += '&action=' + ( update ? 'update-' : 'add-' ) + what + '&' + this.grabInputs( where, ajaxAdd ) + this.inputData;
		var tempObj=this;
		ajaxAdd.addOnComplete( function(transport) {
			var newItems = $A(transport.responseXML.getElementsByTagName(what));
			if ( newItems ) {
				newItems.each( function(i) {
					var id = i.getAttribute('id');
					var exists = $(what+'-'+id);
					if ( exists )
						tempObj.replaceListItem( exists, getNodeValue(i,'response_data'), update );
					else
						tempObj.addListItem( getNodeValue(i, 'response_data') );
					if ( tempObj.showLink )
						tempObj.showLink = id;
				});
				ajaxAdd.myResponseElement.update(tempObj.showLink ? ( "<div id='jumplink' class='updated fade'><p><a href='#" + what + '-' + tempObj.showLink + "'><?php _e('Jump to new item'); ?></a></p></div>" ) : '');
			}
			if ( tempObj.addComplete && typeof tempObj.addComplete == 'function' )
				tempObj.addComplete( what, where, update, transport );
			tempObj.recolorList();
			ajaxAdd.restoreInputs = null;
		});
		if ( !update )
			ajaxAdd.addOnWPError( function(transport) { tempObj.restoreForm(ajaxAdd.restoreInputs); });
		ajaxAdd.request(ajaxAdd.url);
		if ( !update )
			this.clear();
		return false;
	},

	// sends update-what and fields contained in where
	// recieves html with top element having an id like what-#
	ajaxUpdater: function( what, where ) { return this.ajaxAdder( what, where, true ); },

	// sends delete-what and id#
	ajaxDelete: function( what, id ) {
		var ajaxDel = new WPAjax( this.ajaxHandler, this.ajaxRespEl );
		if( ajaxDel.notInitialized() )
			return true;
		var tempObj = this;
		var action = 'delete-' + what + '&id=' + id;
		var idName = what.replace('-as-spam','') + '-' + id;
		ajaxDel.addOnComplete( function(transport) {
			ajaxDel.myResponseElement.update('');
			tempObj.destore(action);
			if( tempObj.delComplete && typeof tempObj.delComplete == 'function' )
				tempObj.delComplete( what, id, transport );
		});
		ajaxDel.addOnWPError( function(transport) { tempObj.restore(action, true); });
		ajaxDel.options.parameters += '&action=' + action + this.inputData;
		ajaxDel.request(ajaxDel.url);
		this.store(action, idName);
		tempObj.removeListItem( idName );
		return false;
	},

	// Toggles class nomes
	// sends dim-what and id#
	ajaxDimmer: function( what, id, dimClass ) {
		ajaxDim = new WPAjax( this.ajaxHandler, this.ajaxRespEl );
		if ( ajaxDim.notInitialized() )
			return true;
		var tempObj = this;
		var action = 'dim-' + what + '&id=' + id;
		var idName = what + '-' + id;
		ajaxDim.addOnComplete( function(transport) {
			ajaxDim.myResponseElement.update('');
			tempObj.destore(action);
			if ( tempObj.dimComplete && typeof tempObj.dimComplete == 'function' )
				tempObj.dimComplete( what, id, dimClass, transport );
		});
		ajaxDim.addOnWPError( function(transport) { tempObj.restore(action, true); });
		ajaxDim.options.parameters += '&action=' + action + this.inputData;
		ajaxDim.request(ajaxDim.url);
		this.store(action, idName);
		this.dimItem( idName, dimClass );
		return false;
	},

	addListItem: function( h ) {
		new Insertion[this.topAdder ? 'Top' : 'Bottom'](this.theList,h);
		this.theList.cleanWhitespace();
		var id = this.topAdder ? this.theList.firstChild.id : this.theList.lastChild.id;
		if ( this.alt )
			if ( ( this.theList.childNodes.length + this.altOffset ) % 2 )
				$(id).addClassName(this.alt);
		Fat.fade_element(id);
	},

	// only hides the element sa it can be put back again if necessary
	removeListItem: function( id, noFade ) {
		id = $(id);
		if ( !noFade ) {
			Fat.fade_element(id.id,null,700,'#FF3333');
			var tempObj = this;
			var func = function() { id.hide(); tempObj.recolorList(); }
			setTimeout(func, 705);
		} else {
			id.hide();
			this.recolorList();
		}
	},

	replaceListItem: function( id, h, update ) {
		id = $(id);
		if ( !update ) {
			id.remove();
			this.addListItem( h );
			return;
		}
		id.replace(h);
		Fat.fade_element(id.id);
	},

	// toggles class
	dimItem: function( id, dimClass, noFade ) {
		id = $(id);
		if ( id.hasClassName(dimClass) ) {
			if ( !noFade )
				Fat.fade_element(id.id,null,700,null);
			id.removeClassName(dimClass);
		} else {
			if ( !noFade )
				Fat.fade_element(id.id,null,700,'#FF3333');
			id.addClassName(dimClass);
		}
	},

	// store an element in case we need it later
	store: function(action, id) {
		if ( !this.dataStore )
			this.dataStore = $H();
		this.dataStore[action] = $(id).cloneNode(true);
	},

	// delete from store
	destore: function(action) { delete(this.dataStore[action]); },

	// restore element from store into existing (possibly hidden) element of same id
	restore: function(action, error) {
		var id = this.dataStore[action].id;
		this.theList.replaceChild(this.dataStore[action], $(id));
		delete(this.dataStore[action]);
		if ( error ) {
			func = function() { $(id).setStyle( { 'background-color': '#FF3333' } ); }
			func(); setTimeout(func, 705); // Hit it twice in case it's still fading.
		}
	},

	// Like Form.serialize, but excludes action and sets up clearInputs
	grabInputs: function( where, ajaxObj ) {
		if ( ajaxObj )
			ajaxObj.restoreInputs = [];
		var elements = Form.getElements($(where));
		var queryComponents = new Array();
		for (var i = 0; i < elements.length; i++) {
			if ( 'action' == elements[i].name )
				continue;
			if ( 'hidden' != elements[i].type && 'submit' != elements[i].type && 'button' != elements[i].type ) {
				this.clearInputs.push(elements[i]);
				if ( ajaxObj )
					ajaxObj.restoreInputs.push([elements[i], elements[i].value]);
			}
			var queryComponent = Form.Element.serialize(elements[i]);
			if (queryComponent) {
				queryComponents.push(queryComponent);
			}
		}
		return queryComponents.join('&');
	},

	// form.reset() can only do whole forms.  This can do subsections.
	clear: function() {
		this.clearInputs.each( function(i) {
			i = $(i);
			if ( 'textarea' == i.tagName.toLowerCase() )
				i.value = '';
			else
				switch ( i.type.toLowerCase() ) {
					case 'password': case 'text':
						i.value = '';
						break;
					case 'checkbox': case 'radio':
						i.checked = false;
						break;
					case 'select': case 'select-one':
						i.selectedIndex = null;
						break;
					case 'select-multiple':
						for (var o = 0; o < i.length; o++) i.options[o].selected = false;
						break;
				}
		});
		this.clearInputs = [];
	},

	restoreForm: function(elements) {
		elements.each( function(i) {
			i[0].value = i[1];
		});
	},

	recolorList: function() {
		if ( !this.alt )
			return;
		var alt = this.alt;
		var offset = this.altOffset;
		var listItems = $A(this.theList.childNodes).findAll( function(i) { return i.visible() } );
		listItems.each( function(i,n) {
			if ( ( n + offset ) % 2 )
				i.removeClassName(alt);
			else
				i.addClassName(alt);
		});
	}
});

//No submit unless code returns true.
function killSubmit ( code, e ) {
	e = e ? e : window.event;
	if ( !e ) return;
	var t = e.target ? e.target : e.srcElement;
	if ( ( 'text' == t.type && e.keyCode == 13 ) || ( 'submit' == t.type && 'click' == e.type ) ) {
		if ( ( 'string' == typeof code && !eval(code) ) || ( 'function' == typeof code && !code() ) ) {
			e.returnValue = false; e.cancelBubble = true; return false;
		}
	}
}
//Generic but lame JS closure
function encloseFunc(f){var a=arguments[1];return function(){return f(a);}}
