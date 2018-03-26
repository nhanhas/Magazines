//Framework Utils

//#Remote
app.service('FrameworkUtils', ['$http', function($http) {

   /**
    * Remote Services Utils
    */
    //GET Type
    this.Http_GET  = function(serviceURL){
        return $http({
                    method: 'GET',
                    url: serviceURL
                }).then(function successCallback(response) {
                    return response;
                }, function errorCallback(response) {
                    return 'error';
                });
    }

    //POST Type
    this.Http_POST  = function(serviceURL, data){
        return $http.post(serviceURL, data).then(function successCallback(response) {
                    return response;
                }, function errorCallback(response) {
                    console.log(response);
                    return 'error';
                });
    }


    //Parameters from QueryString 
    this.getParameterByName = function (name, url) {
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

}]);



