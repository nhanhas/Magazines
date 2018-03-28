app
    .controller('LoginController', ['$rootScope', '$scope', '$location','FrameworkUtils', 'StoreService', function($rootScope, $scope, $location, FrameworkUtils, StoreService) {

        $scope.error = undefined;

        $scope.credentials = {
            userCode : 'jb',
            password : '1', 
            company : '',
            applicationType : 'HYU45F-FKEIDD-K93DUJ-ALRNJE'
        }

        $scope.login = function (path) {
            console.log($scope.credentials);
            StoreService.userLogin($scope.credentials).then(function(result){
                if(result.code === 0){
                    $scope.error = undefined;
                    
                    //Set at rootscope
                    $rootScope.credentials = $scope.credentials;
                    //navigate
                    $location.path('/home');
                }else{
                    $scope.error = 'Erro de login. Verifique se os seus dados estão corretos.'
                }
            });
            
        };

    }]);
 