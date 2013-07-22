;(function($, window, document, undefined){
/**
* @AUTHOR Francesco Delacqua
* proxy per il verbo DELETE per interagire con il database
*/

var pluginName = "mapManager",
	defaults = {
		map: false,
		geocoder: true,
		geocoderMarker: false,
		markerPosition: false,
		geocoderButtonSelector : '#geocoder-btn',
		geocoderTextSelector : 'input[name=geocoderAddress]',
		latitudeFieldSelector : 'input[name=latitudine]',
		longitudeFieldSelector : 'input[name=longitudine]',
		maxOverlays: 1
	};

function Plugin(element, options) {
	this.element = element;
	this.options = $.extend( {}, defaults, options);
	this._defaults = defaults;

	this.init();
}

Plugin.prototype.init = function() {
	var self = this;

	if(!self.options.map) return alert("errore: devi delle opzioni valide per una mappa");

	google.maps.visualRefresh = true;

	self.map = new google.maps.Map(self.element, self.options.map);

	self.overlay = {}; //contiene gli overlay aggiunti alla mappa
	
	//GEOCODER
	//==================================
	if(self.options.geocoder) self.addGeocoder();

	self.bindEvents();

};

Plugin.prototype.addGeocoder = function(){
	var self = this,
		center;

	if(!self.geocoder) self.geocoder = new google.maps.Geocoder();
	
	self.$latitudeField = $(self.options.latitudeFieldSelector);
	self.$longitudeField = $(self.options.longitudeFieldSelector);
	self.$geocoderButton = $(self.options.geocoderButtonSelector);
	self.$geocoderText = $(self.options.geocoderTextSelector);

	if(self.options.markerPosition) {
		center = new google.maps.LatLng(
			self.options.markerPosition.latitude,
			self.options.markerPosition.longitude
		);
		self.map.setCenter(center);
	}

	self.infoWindow = new google.maps.InfoWindow();
	self.geocoderMarker = new google.maps.Marker({
		position: self.map.getCenter(),
		title: 'denominazione',
		draggable: true,
	});

	if(self.options.geocoderMarker) {
		self.geocoderMarker.setMap(self.map);
		self.updateLatLng(self.geocoderMarker.getPosition());
	}

	self.bindGeocoderEvents();

};

Plugin.prototype.showAddress = function(address, marker) {
	var self = this,
		geocoder_request;

	if (self.geocoder) {
		geocoder_request = {
			address: address,
			region: 'it'
		};
		self.geocoder.geocode(
			geocoder_request,
			function(GeocoderResult, GeocoderStatus) {
				var result = GeocoderResult.pop(),
					position = result.geometry.location,
					info = '',
					i;

				self.geocoderMarker.setPosition(position);
				self.geocoderMarker.setTitle(result.address_components.long_name);
				self.map.setCenter(position);

				/*for(i in result.address_components[0]){
					info += '<span>'+i+": "+result.address_components[0][i]+'</span><br>'
				}*/
				info += result.formatted_address;

				self.updateInfo(info, position);
				self.infoWindow.open(self.map);
			}
		);
	}
};

Plugin.prototype.getClientLocation = function() {
	var self = this,
		clientLocation,
		image;

	if (navigator.geolocation) {
		// console.log("geolocation enabled");
		navigator.geolocation.getCurrentPosition(
			function(location){
				 image = {
					url: '/img/clientposition.png',
					// This marker is 20 pixels wide by 32 pixels tall.
					size: new google.maps.Size(20, 20),
					// The origin for this image is 0,0.
					origin: new google.maps.Point(0,0),
					// The anchor for this image is the base of the flagpole at 0,32.
					anchor: new google.maps.Point(10, 9)
				  };

				clientLocation = new google.maps.LatLng(location.coords.latitude, location.coords.longitude);
				self.clientMarker = new google.maps.Marker({
					icon: image,
					position: clientLocation,
					title: 'posizione attuale',
					draggable: false,
					map: self.map
				});
				// console.log(location, clientLocation);
			}, function(error){
				// console.log(error);
			}
		);
	} else {
		// console.log("no location, no party");
	}
};

Plugin.prototype.redraw = function() {
	var map = this.map,
		bounds = map.getBounds();

	// map.fitBounds(bounds);
	google.maps.event.trigger(map, 'resize');
	return this;
};

Plugin.prototype.centerMap = function(latLng) {
	var map = this.map,
		center = map.getCenter();

	map.setCenter(center);
	return this;
};

Plugin.prototype.updateInfo = function(content, position) {
	var self = this;
	self.infoWindow.setContent(content);
	if(position && position.lat()){
		self.infoWindow.setPosition(position);
	}
};

Plugin.prototype.updateLatLng = function(position){
	if(this.$latitudeField) this.$latitudeField.val(position.lat());
	if(this.$longitudeField) this.$longitudeField.val(position.lng());
};


Plugin.prototype.bindGeocoderEvents = function() {
	var self = this;

	self.$geocoderText.keypress(function(e) {
		var enterKey = 13;

		if(e.which == enterKey) {
			self.$geocoderButton.trigger('click');
			e.preventDefault();
		}
	});

	google.maps.event.addDomListener(self.$geocoderButton[0], 'click', function() {
		self.showAddress(self.$geocoderText.val(), self.geocoderMarker);
	});

	//GEOCODER EVENTS
	//==================================
	if(self.options.geocoderMarker) {
		google.maps.event.addListener(self.geocoderMarker, 'position_changed', function() {
			var position = this.getPosition();
			self.updateLatLng(position);
		});
		google.maps.event.addListener(self.geocoderMarker, 'click', function() {
			self.infoWindow.open(self.map);
		});
		google.maps.event.addListener(self.geocoderMarker, 'drag', function() {
			self.infoWindow.close();
		});
		google.maps.event.addListener(self.geocoderMarker, 'dragend', function() {
			var position = this.getPosition(),
				content = position.lat()+', '+position.lng();

			self.updateInfo(content,position);
		});
	}
};

// DRAWING MANAGEMENT

Plugin.prototype.addDrawingManager = function() {
	var self = this;

		self.overlayOptions = {
			strokeWeight: 1,
			clickable: true,
			editable: false,
			draggable: true
		};

	try {
		self.drawingManager = new google.maps.drawing.DrawingManager({
			drawingMode: google.maps.drawing.OverlayType.MARKER,
			drawingControl: true,
			drawingControlOptions: {
				position: google.maps.ControlPosition.TOP_CENTER,
				drawingModes: [
					google.maps.drawing.OverlayType.MARKER,
					google.maps.drawing.OverlayType.CIRCLE,
					google.maps.drawing.OverlayType.POLYGON,
					google.maps.drawing.OverlayType.POLYLINE,
					google.maps.drawing.OverlayType.RECTANGLE
				]
			},
			markerOptions: {
				// icon: 'images/beachflag.png'
				clickable: true,
				draggable: true
			},
			circleOptions: self.overlayOptions,
			polygonOptions: self.overlayOptions,
			polylineOptions: self.overlayOptions,
			rectangleOptions: self.overlayOptions
		});

		self.drawingManager.setMap(self.map);

		self.bindDrawingEvents();

		return self.drawingManager;
	}catch(e) {

		alert([
			'errore. ',
			'assicurati di aver caricato la libreria "drawing":',
			'\<script src="https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=drawing"\>\<\/script\>',
		].join(''));
	}
}

Plugin.prototype.addOverlay = function(type, overlay) {
	var self = this,
		id = [type, (new Date()).getTime()].join('_');

		overlay.id = id; //salvo l'id anche nell'overlay
		overlay.type = type; //salvo il type anche nell'overlay

		self.overlay[id] = overlay; //memorizzo un riferimento all'overlay

		//quando aggiungo un overlay gli associo degli eventi
		google.maps.event.addListener(overlay, 'click', function(event) {
			self.selectOverlay(overlay, event);
		});

		google.maps.event.addListener(overlay, 'rightclick', function(event) {
			if(event.vertex) return this.getPath().removeAt(event.vertex);
			if(this.id) self.removeOverlay(this.id);
			event.stop();
		});


		google.maps.event.addListener(overlay, 'bounds_changed', function(event) {
			// console.log("bounds_changed");
			self.updateGeometry(overlay);
		});
		google.maps.event.addListener(overlay, 'paths_changed', function(event) {
			// console.log("paths_changed");
		});
		google.maps.event.addListener(overlay, 'path_changed', function(event) {
			// console.log("path_changed");
		});
		google.maps.event.addListener(overlay, 'center_changed', function(event) {
			// console.log("center_changed");
		});

		//gestione editing dei poligoni
		if(overlay.getPath) {
			google.maps.event.addListener(overlay.getPath(), 'insert_at', function(number) {
				// console.log("insert_at", number, this);
				self.updateGeometry(overlay);
			});
			google.maps.event.addListener(overlay.getPath(), 'set_at', function(number, element) {
				// console.log("set_at", number, element, this);
				// console.log(element.lat());
				self.updateGeometry(overlay);
			});
			google.maps.event.addListener(overlay.getPath(), 'remove_at', function(number, element) {
				// console.log("remove_at", number, element, this);
				self.updateGeometry(overlay);
			});
		}

		//gestione trascinamento
		google.maps.event.addListener(overlay, 'drag', function(event) {
			// console.log("drag");
			self.updateGeometry(overlay);
		});
		google.maps.event.addListener(overlay, 'dragend', function(event) {
			// console.log("dragend");
			self.updateGeometry(overlay);
		});

		return id;
};

/**
* salva il valore della geometria nell'overlay
* segnala l'aggiornamento con l'evento "geometry_changed"
* @param object overlay marker, polyline, polygon, square, circle
*/
Plugin.prototype.updateGeometry = function(overlay) {
	if(overlay) {
		overlay.geometry = this.getGeometry(overlay);;
	}else {
		overlay = {};
	}

	$(this.element).trigger('geometry_changed', [overlay]);
};

/** conversione da well known text ad array di punti
* ogni punto è un object del tipo {lat: float, lng: float}
* @param string geometry una geometria in formato well nown text
* @return array points array di punti {lat: float, lng: float} 
*/
Plugin.prototype.parseWKT = function(geometry) {
	try {
		var re, matches, match, pointsAsStrings, points;

		if(typeof geometry == 'string') {
			re = /[a-z]+\(\(?([\d\. ,]+)\)?\)/i;
			matches = geometry.match(re);

			if(matches && matches.length>0) {
				match = matches.pop();
				pointsAsStrings = match.split(',');
				points = $.map(pointsAsStrings, function(element, index){
					var pointArray = element.split(' '),
						point = new google.maps.LatLng(
							(pointArray[0]) ? (pointArray[0]) : 0,
							(pointArray[1]) ? (pointArray[1]) : 0
						);
					return point;
				});
			}

		}
		return points;
	} catch(e) {
		alert(e);
	}
};

Plugin.prototype.drawGeometry = function(type, radius, geometry, options) {
	var self = this,
		points = self.parseWKT(geometry),
		overlay,
		overlayOptions = $.extend( {}, self.overlayOptions, options);
	try {

		switch(type) {
			case'marker':
				overlay = new google.maps.Marker(overlayOptions);
				overlay.setPosition(points[0]);
				break;
			case'polyline':
				overlay = new google.maps.Polyline(overlayOptions);
				overlay.setPath(points);
				break;
			case'polygon':
				var first = points[0],
					last = points[points.length-1];
				if(first.toString() == last.toString()) {
					points.pop(); //elimino l'ultimo punto se uguale al primo
				}
				overlay = new google.maps.Polygon(overlayOptions);
				overlay.setPath(points);
				break;
			case'rectangle':
				var sw = points[0],
					ne = points[2],
					bounds = new google.maps.LatLngBounds(sw,ne);

				overlay = new google.maps.Rectangle(overlayOptions);
				overlay.setBounds(bounds);
				break;
			case'circle':
				overlay = new google.maps.Circle(overlayOptions);
				overlay.setCenter(points[0]);
				overlay.setRadius(parseFloat(radius));
				break;
			default:
				break;
		}
		if(overlay) {

			overlay.setMap(self.map);

			if(self.drawingManager) self.drawingManager.setDrawingMode(null);

			self.addOverlay(type,overlay);
		}
	}catch(e) {
		console.error(e+' '+'errore dati');
	}
};

Plugin.prototype.deselectOverlay = function(overlay) {
	if(overlay.default_options) overlay.setOptions(overlay.default_options);
	overlay.selected = false;
};

Plugin.prototype.selectOverlay = function(overlay, event) {
	var self = this,
		i, _overlay;

	// deseleziona gli altri overlay della mappa
	for(i in self.overlay) {
		_overlay = self.overlay[i];
		if(overlay !== _overlay) self.deselectOverlay(_overlay);
	}

	// salvo i valori di default dell'overlay
	if(!overlay.default_options) {
		overlay.default_options = {
			editable: overlay.editable,
			fillColor: overlay.fillColor,
			fillOpacity: overlay.fillOpacity,
			strokeColor: overlay.strokeColor,
			strokeOpacity: overlay.strokeOpacity,
			strokeWeight: overlay.strokeWeight,
			zIndex: 1
		};
	}

	// imposto un colore casuale per la selezione e lo salvo nell'overlay
	if(!overlay.selected_fillColor) overlay.selected_fillColor = getRandomColor();
	
	if(!overlay.selected) {
		// applico lo stile all'overlay evidenziato
		overlay.setOptions({
			fillColor: overlay.selected_fillColor,
			fillOpacity: 0.5,
			zIndex: 2,
			editable: true,
		});

		overlay.selected = true;

		self.updateGeometry(overlay);
	}else {
		// deseleziona un overlay se selezionato
		self.deselectOverlay(overlay);
	}

};

// genera un colore casuale compatibile con lo standard css
function getRandomColor() {
	var rgb,
		randomNumber = function(min,max) {
			return Math.floor(Math.random() * (max - min + 1)) + min;
		};

	rgb = [
		randomNumber(0,255),
		randomNumber(0,255),
		randomNumber(0,255)
	];

	return 'rgb(' + rgb.join(',') + ')';
}

/**
* @param object overlay l'overlay di google maps. il type gli viene assegnato in addOverlay
* @return string geometry_WKT la geometria in formato WKT (well known text)
*/
Plugin.prototype.getGeometry = function(overlay) {
	var geometry_WKT,
		coords = this.getCoords(overlay),
		coordsAsText = coords.join(',');

	switch(overlay.type) {
		case 'polyline':
			geometry_WKT = 'LINESTRING('+coordsAsText+')';
			break;
		case 'polygon':
		case 'rectangle':
			geometry_WKT = 'POLYGON(('+coordsAsText+'))';
			break;
		case 'circle':
		case 'marker':
			geometry_WKT = 'POINT('+coordsAsText+')';
			break;
		default:
			geometry_WKT = '';
	}
	return geometry_WKT;
};

/**
* @param object overlay
* @return array coords i punti della geometria. ogni punto è una stringa con lat e lng separati da spazio
*/
Plugin.prototype.getCoords = function(overlay) {
	var points = [],
		path, bounds, coords;

	switch(overlay.type) {
		case 'polygon':
			path = overlay.getPath();
			points = path.getArray().slice();
			points.push(points[0]); //aggiungo il primo punto per chiudere il poligono
			break;
		case 'polyline':
			path = overlay.getPath();
			points = path.getArray().slice();
			break;
		case 'rectangle':
			var ne, nw, sw, se;
			bounds = overlay.getBounds();

			sw = bounds.getSouthWest();
			ne = bounds.getNorthEast();
			se = new google.maps.LatLng(ne.lat(), sw.lng());
			nw = new google.maps.LatLng(sw.lat(), ne.lng());

			points = [sw,se,ne,nw,sw];
			break;
		case 'circle':
			points = [overlay.getCenter()];
			// points = overlay.getRadius();
			break;
		case 'marker':
			points = [overlay.getPosition()];
			break;
		default:
			points = [];
	}

	coords = $.map(points,function(value, index){
		return [value.lat(), value.lng()].join(' ');
	});

	return coords;
}



Plugin.prototype.findOverlay = function(id) {
	return this.overlay[id];
};

/**
* rimuove un overlay dalla mappa
*/
Plugin.prototype.removeOverlay = function(id) {
	this.overlay[id].setMap(null);
	delete this.overlay[id];

	this.updateGeometry();
};

/**
* @param string type tipo di overlay
* @return array overlays elenco di overlay di un certo tipo
*/
Plugin.prototype.getOverlays = function(type) {
	var self = this,
		overlays = [],
		overlay,
		type = type || 'any',
		available_types = ['circle','marker','polygon','polyline','rectangle'],
		regExp;

		if($.inArray(type,available_types)>-1 || type=='any') {
			overlays = $.map(self.overlay, function(value, key){
				regExp = (type!=='any') ? new RegExp('^'+type+'_','i') : new RegExp('.*','i');
				if(regExp.test(key)) return value;
			});
		}
		return overlays;
};

Plugin.prototype.bindDrawingEvents = function() {
	var self = this;

	google.maps.event.addListener(self.drawingManager, 'overlaycomplete', function(event) {
		if (event.type == google.maps.drawing.OverlayType.POLYGON) {
			var path = event.overlay.getPath();
		}
		self.addOverlay(event.type, event.overlay);
		
		self.updateGeometry(event.overlay);

		this.setDrawingMode(null);

	});


	google.maps.event.addListener(self.map, 'click', function(event) {


	});

};

Plugin.prototype.bindEvents = function() {
	var self = this;

};

	
$.fn[pluginName] = function(options){
	return this.each(function(){
		if ( !$.data(this, "plugin_" + pluginName )) {
			$.data( this, "plugin_" + pluginName,
			new Plugin( this, options )); //salvo un riferimento alla plugin nell'elemento
		}
	});
};

}( jQuery, window, document ));