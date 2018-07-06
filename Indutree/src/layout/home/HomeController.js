app
    .controller('HomeController', ['$rootScope', '$scope', '$controller', '$timeout', '$location', '$http','$q', '$filter', 'FrameworkUtils', 'StoreService',   function($rootScope, $scope, $controller,$timeout, $location, $http, $q, $filter, FrameworkUtils, StoreService) {

        /**
         * Controller Variables
         */
        $scope.menuSelected = undefined;
        $scope.productList = undefined;
        $scope.clientList = undefined;
        $scope.filters = {
            searchClient : '',
            searchHeadquarter: '',
            transportadora: '',
            segmento: '',
            sendEmail: true
        };
        $scope.loading = false;
        $scope.waybillDateHour = {
            loadDate : '',
            loadHour : ''
        }

        //FILTERS
        //Transport list
        $scope.transportadoraList = [
          
        ];

        //Segmento list
        $scope.segmentoList = [
          
        ];

        //Invoicing
        $scope.headquartersList = undefined;
        $scope.loadingInvoicing = false;

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

        

        /**
         * Initialize main function
         */
        $scope.initialize = function(){
            var promises = [];//push all promises
            //promises.push();//Get Catalog of Products 
            
            //Get Filters
            $scope.getFilters('a_segs');
			//we will get transp filter in a_segs

    

            //get All Promises
            return $q.all(promises).then(function(result) {
				
				
              
            });        
        }



       /**
        * Controller Functions 
        */
        $scope.selectAll = function(){
            let filteredClients = $filter('filter')($scope.clientList, $scope.filters.searchClient);
            
			//let filteredClients = $filter('filter')($scope.clientList, $scope.filters.searchClient);     
            
            //#1 - segmento
            filteredClients.forEach(function(client) {
                //if segmento and transp is undefined, select all
                if( ($scope.filters.segmento == null || $scope.filters.segmento.campo == undefined) && ( $scope.filters.transportadora == null || $scope.filters.transportadora.campo  == undefined )){
                    client.selected = true;
                }

                //otherwise select based on does 2 filters
                if( ( $scope.filters.segmento == null || $scope.filters.segmento.campo == undefined || client.segmento == $scope.filters.segmento.campo)
                     && ($scope.filters.transportadora == null || $scope.filters.transportadora.campo  == undefined || client.trasportadora == $scope.filters.transportadora.campo)){
                    client.selected = true;
                }
                
            });
			
			/*filteredClients.forEach(function(client) {
                client.selected = true;
            });*/

        }

        $scope.menuSelection = function(option){
            $scope.clientList = [];
            $scope.productList = [];
            $scope.menuSelected = option;

            switch (option) {
                case 1:
                    $scope.getProducts();                    
                    break;
                case 2:
                    //$scope.getHeadquarters();
                    $scope.getProducts(); 
                    break;
                
            }

        }
    
        //Get Products from Drive FX
        $scope.getProducts = function(){
            StoreService.getProductsService($scope.credentials).then(function(result){
                if(result.code === 0){
                    console.log(result.data);
                    $scope.productList = result.data;
                    $scope.getClients(); 
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

        //Get Filters from Drive FX
        $scope.getFilters = function(filterRequested){
            StoreService.getFiltersService($scope.credentials, filterRequested).then(function(result){
                if(result.code === 0 && result.data.length > 0){
                    console.log(result.data);
                    if(filterRequested == 'a_segs'){
                        $scope.segmentoList = result.data[0].dytables;
						$scope.getFilters('u6525_indutree_transp');
                    }else{
                        $scope.transportadoraList = result.data[0].dytables;
                    }       
                }else{
                    console.log("filter " + filterRequested + " with empty result");
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
                StoreService.generateWaybillService($scope.credentials, productsToWayBill, clientsToWayBill, $scope.waybillDateHour.loadDate, $scope.waybillDateHour.loadHour).then(function(result){
                    $scope.loading = false;
                    $scope.waybilled = true;
                    if(result.code === 0){
                        console.log(result.data);                        
                    }else{
                        
                    }
                });
            }
        }

        $scope.requestInvoices = function(){
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
                    clientsToWayBill.push({no : client.no, estab : client.estab, invoiceHeadquarters: client.invoiceheadquarter});
            });

            if(productsToWayBill.length > 0){

                //call Service
                StoreService.generateInvoicing($scope.credentials, productsToWayBill, clientsToWayBill, $scope.filters.sendEmail).then(function(result){
                    $scope.loading = false;
                    $scope.waybilled = true;
                    if(result.code === 0){ 
                        console.log(result.data);                        
                    }else{
                        
                    }
                });
            }
        }

        //Get All HeadQuarters
        $scope.getHeadquarters = function(){
            StoreService.getHeadquartersService($scope.credentials).then(function(result){
                if(result.code === 0){
                    console.log(result.data);
                    $scope.headquartersList = result.data;
                    
                }else{
                }
            });
        }

        //Deprecated
        $scope.requestInvoicing = function(){
           
            let clientsToInvoice = []; // if is empty, allow ALL, otherwise none
            $scope.loadingInvoicing = true;
            $scope.invoiced = false; //for green message
           
            //#1 - iterate clients
            $scope.headquartersList.forEach(function(client) {
                if(client.selected)
                    clientsToInvoice.push({no : client.no, estab : client.estab});
            });

            //call Service
            StoreService.generateInvoicing($scope.credentials, clientsToInvoice).then(function(result){
                $scope.loadingInvoicing = false;
                $scope.invoiced = true;
                if(result.code === 0){
                    console.log(result.data);                        
                }else{
                    
                }
            });
        }

        $scope.selectAllHeadquarters = function(){
            let filteredClients = $filter('filter')($scope.headquartersList, $scope.filters.searchHeadquarter);
            filteredClients.forEach(function(client) {
                client.selected = true;
            });

        }

        //Initialize!
        $scope.initialize();

    }]);


