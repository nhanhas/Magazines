/**
 * This Service will hold all the needed Requests
 * and some manipulations to return to Frontend the
 * full product catalog 
 */
app.service('StoreService', ['$http', 'FrameworkUtils', function($http, FrameworkUtils) {

    //Store Service URL    
    this.baseURL = '../server';
   
    //POST Login user
    this.userLogin  = function(credentials){
       
        let serviceURL = this.baseURL + '/DRIVE_userLogin.php';
        let parameter = { credentials : credentials };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }

    //POST get products
    this.getProductsService  = function(credentials){
       
        let serviceURL = this.baseURL + '/DRIVE_getProducts.php';
        let parameter = { credentials : credentials };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }

    //POST get Client
    this.getClientsService  = function(credentials){
       
        let serviceURL = this.baseURL + '/DRIVE_getClients.php';
        let parameter = { credentials : credentials };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }

    //POST get Client
    this.getHeadquartersService  = function(credentials){
    
        let serviceURL = this.baseURL + '/DRIVE_getHeadquarters.php';
        let parameter = { credentials : credentials };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
            return result.data;
        });
    }

    this.getProductsByBaseService  = function(credentials, baseRef){
       
        let serviceURL = this.baseURL + '/DRIVE_getProductsByBase.php';
        let parameter = { credentials : credentials, baseRef : baseRef };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }

    this.generateWaybillService  = function(credentials, products, clients, loadDate, loadHour){
       
        let serviceURL = this.baseURL + '/DRIVE_generateWaybill.php';
        let parameter = {   credentials : credentials, 
                            products : products,
                            clients : clients, 
                            waybillConfig : {
                                loadDate : loadDate,
                                loadHour : loadHour
                            } 
                        };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }
    
    this.generateInvoicing = function(credentials, products, clients){
        let serviceURL = this.baseURL + '/DRIVE_generateInvoice.php';
        let parameter = {   credentials : credentials,
                            clients : clients,
                            products : products
                        };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }

    //POST get filters
    this.getFiltersService  = function(credentials, entity){
       
        let serviceURL = this.baseURL + '/DRIVE_getFilters.php';
        let parameter = { credentials : credentials, filterRequested : entity };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }

}]);




