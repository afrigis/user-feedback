module.exports = {
	options: {
		curly     : true,
		eqeqeq    : true,
		immed     : true,
		latedef   : true,
		newcap    : true,
		noarg     : true,
		sub       : true,
		undef     : true,
		boss      : true,
		eqnull    : true,
		browser   : true,
		devel     : true,
		browserify: true,
		globals   : {
			jQuery       : true,
			Backbone     : false,
			_            : false,
			ajaxurl      : false,
			user_feedback: false,
			html2canvas  : false
		}
	},
	all    : [
		'js/src/**/*.js',
		'js/test/**/*.js'
	]
}
