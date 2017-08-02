module.exports = function( grunt ) {

	grunt.initConfig({

		concat: {
			dist: {
				src: [
					'assets/js/tagging.js',
					'assets/js/peepsotags.js'
				],
				dest: 'assets/js/bundle.js'
			}
		},

		uglify: {
			dist: {
				options: {
					report: 'none',
					sourceMap: true
				},
				files: [{
					src: [ 'assets/js/bundle.js' ],
					expand: true,
					ext: '.min.js'
				}]
			}
		}

	});

	grunt.loadNpmTasks( 'grunt-contrib-concat' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );

	grunt.registerTask( 'default', [
		'concat',
		'uglify'
	]);

};
