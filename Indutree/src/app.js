var app = angular.module("App", ['ngRoute', 'ui.bootstrap', 'pascalprecht.translate']);

//Routes Configuration 
app.config(['$routeProvider', function($routeProvider) {
    $routeProvider.
        when('/home', {
            templateUrl: 'layout/home/home.html',
            controller: 'HomeController'
        }).
        when('/login', {
            templateUrl: 'layout/login/login.html',
            controller: 'LoginController'
        }).
        otherwise({
            redirectTo: '/login' 
        });
}])

//Prepare Translations
app.config(['$translateProvider', function($translateProvider) {

    //Parameters from QueryString 
    let getParameterByName = function (name, url) {
        if (!url) {
            url = window.location.href;
        }
        name = name.replace(/[\[\]]/g, "\\$&");
        var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, " "));
    }

    //prepare translations
    let lang = getParameterByName('lang') || 'pt';

    $.getJSON("framework/translations-" + lang + ".json", function(json) {
            $translateProvider.translations(lang, json)
    });
    
    //select language
    $translateProvider.preferredLanguage(lang);

}])



