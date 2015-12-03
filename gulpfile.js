var gulp = require('gulp'),
	sass = require('gulp-sass'),
	uglify = require('gulp-uglify'),
	minifyCss = require('gulp-minify-css'),
	rename = require("gulp-rename"),
	concat = require("gulp-concat"),
	inlineCss = require('gulp-inline-css'),
	convertEncoding = require('gulp-convert-encoding'),
	order = require("gulp-order");


// Compile Sass
gulp.task('sass', function() {
    return gulp.src('src/sass/**/*.scss')
        .pipe(sass())
        .pipe(gulp.dest('src/css/'));
});

gulp.task('css', ['sass'], function() {
	return gulp.src('src/css/**/*.css')
		.pipe(order(['src/css/**/*.css', 'src/css/crucio.css']))
    	.pipe(concat('crucio.css'))
    	.pipe(minifyCss())
    	.pipe(rename({ suffix: '.min' }))
		.pipe(gulp.dest('public/css/'));
})

// Compile JS
gulp.task('js', function() {
	return gulp.src('src/js/**/*.js')
    	.pipe(concat('crucio.js'))
    	.pipe(uglify({ mangle: false }))
    	.pipe(rename({ suffix: '.min' }))
    	.pipe(convertEncoding({to: 'iso-8859-15'}))
		.pipe(gulp.dest('public/js/'));
})

// Compile Mail Templates
gulp.task('mail', function() {
	return gulp.src('src/mail-templates/**/*.html')
    	.pipe(inlineCss())
		.pipe(gulp.dest('public/mail-templates/'));
})

// Watch Files For Changes
gulp.task('serve', function() {
	gulp.watch('src/sass/**/*.scss', ['sass', 'css']);
	gulp.watch('src/js/**/*.js', ['js']);
	gulp.watch('src/mail-templates/**/*.html', ['mail']);
});

// Default Task
gulp.task('default', ['serve']);