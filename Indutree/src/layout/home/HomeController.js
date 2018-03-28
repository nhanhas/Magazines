app
    .controller('HomeController', ['$rootScope', '$scope', '$controller', '$timeout', '$location', '$http','$q', 'FrameworkUtils', 'StoreService',   function($rootScope, $scope, $controller,$timeout, $location, $http, $q, FrameworkUtils, StoreService) {

         
        /**
         * Controller Variables
         */
        $scope.menuSelected = undefined;
        $scope.productList = undefined;
        $scope.clientList = undefined;
        $scope.searchClient = '';
                
        //TODO - change this
        /*$scope.credentials = {
            userCode : 'jb',
            password : '1', 
            company : '',
            applicationType : 'HYU45F-FKEIDD-K93DUJ-ALRNJE'
        }*/

        $rootScope.credentials = $scope.credentials;
        if(!$rootScope.credentials){
            //navigate
            $location.path('/login');
        }

        $scope.loadDate = '2018-03-27';
        $scope.loadHour = '19:55';
        $scope.loading = false;

        /**
         * Initialize main function
         */
        $scope.initialize = function(){
            var promises = [];//push all promises
            //promises.push();//Get Catalog of Products 
            


            //get All Promises
            $q.all(promises).then(function(result) {

              
            });        
        }



       /**
        * Controller Functions 
        */
        $scope.menuSelection = function(option){
            $scope.menuSelected = option;

            switch (option) {
                case 1:
                    $scope.getProducts();
                    $scope.getClients(); 
                    break;
                case 2:
                    
                    break;
                
            }

        }
    
        //Get Products from Drive FX
        $scope.getProducts = function(){
            StoreService.getProductsService($scope.credentials).then(function(result){
                if(result.code === 0){
                    console.log(result.data);
                    $scope.productList = result.data;
                }else{
                    
                }
            });
        }
        //Get Clients from Drive FX
        $scope.getClients = function(){
            StoreService.getClientsService($scope.credentials).then(function(result){
                if(result.code === 0){
                    console.log(result.data);
                    $scope.clientList = result.data;
                    
                }else{
                }
            });
        }

        //Get Products from Drive FX
        $scope.expandProduct = function(baseProduct){
            if(baseProduct.expanded)
                return;

            baseProduct.expanded = true;
            //get products generated
            StoreService.getProductsByBaseService($scope.credentials, baseProduct.baseref).then(function(result){
                if(result.code === 0){
                    console.log(result.data);
                    baseProduct.generated = result.data;
                }else{
                    
                }
            });
        }

        $scope.requestWaybill = function(){
            let productsToWayBill = [];
            let clientsToWayBill = []; // if is empty, allow ALL, otherwise none
            $scope.loading = true;
            $scope.waybilled = false; //for green message

            //#1 - iterate base products
            $scope.productList.forEach(function(baseProduct) {
                if(baseProduct.generated && baseProduct.generated.length > 0){
                    //#2 - iterate their collection
                    baseProduct.generated.forEach(function(product) {
                        if(product.selected)
                            productsToWayBill.push(product.ref);
                    });
                }
            });

            //#2 - iterate clients
            $scope.clientList.forEach(function(client) {
                if(client.selected)
                    clientsToWayBill.push({no : client.no, estab : client.estab});
            });

            if(productsToWayBill.length > 0){
                
                //call Service
                StoreService.generateWaybillService($scope.credentials, productsToWayBill, clientsToWayBill, $scope.loadDate, $scope.loadHour).then(function(result){
                    $scope.loading = false;
                    $scope.waybilled = true;
                    if(result.code === 0){
                        console.log(result.data);                        
                    }else{
                        
                    }
                });
            }
        }

        //Initialize!
        $scope.initialize();

    }]);


