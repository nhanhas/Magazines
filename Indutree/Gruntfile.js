module.exports = function(grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        sass: {
            options: {
                sourceMap: false,
                sourceComments: false
            },
            src: {
                files: [{
                    expand: true,
                    cwd: 'src/',
                    src: ['**/*.scss'],
                    dest: 'deploy/',
                    ext: '.css'
                }]
            }
        },
        watch: {
            options:{
                event: 'all'
            },
            css: {
                files: ['src/**/*.scss'],
                tasks: ['sass']
            },
            js:{
                files: ['src/**/*.js'],
                tasks: ['copy:scripts']
            },
            html:{
                files: ['src/**/*.html'],
                tasks: ['copy:html']
            }
        },
        copy: {
            libs: {
                files:[{
                    expand: true,
                    cwd: 'bower_components/',
                    src: ['**'],
                    dest: 'deploy/libs'
                }]
            },
            scripts: {
                files:[{
                    expand: true,
                    cwd: 'src/',
                    src: ['**','!**/*.scss', '!**/*.html'],
                    dest: 'deploy/'
                }]
            },
            html: {
                files:[{
                    expand: true,
                    cwd: 'src/',
                    src: ['**','!**/*.scss', '!**/*.js'],
                    dest: 'deploy/'
                }]
            }
        },
        uglify: {
            build: {
                src: 'src/<%= pkg.name %>.js',
                dest: 'deploy/<%= pkg.name %>.min.js'
            }
        }


    });

    /*=====================================
     =        Default Configuration        =
     =====================================*/
    //Sass
    grunt.loadNpmTasks('grunt-sass');
    //Watch
    grunt.loadNpmTasks('grunt-contrib-watch');
    //Copy to deploy
    grunt.loadNpmTasks('grunt-contrib-copy');
    //Uglify
    grunt.loadNpmTasks('grunt-contrib-uglify');

    /*=====================================
     =        TASKS Resgistration         =
     =====================================*/
    // $ grunt
    grunt.registerTask('default',['dev']);

    //--------------------------------------

    //copy All project to DEPLOY
    grunt.registerTask('toDeploy', 'Start coping source files', function() {
        grunt.log.writeln('Currently building all project to deploy...');
        grunt.task.run(['copy']);
    });

    //compile sass
    grunt.registerTask('styles', 'Start compiling sass files', function() {
        grunt.log.writeln('Currently compiling sass to deploy...');
        grunt.task.run(['sass']);
    });

    //default custom task
    grunt.registerTask('dev',['toDeploy','styles','watch']);


}