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

    this.getProductsByBaseService  = function(credentials, baseRef){
       
        let serviceURL = this.baseURL + '/DRIVE_getProductsByBase.php';
        let parameter = { credentials : credentials, baseRef : baseRef };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }

    this.generateWaybillService  = function(credentials, products, loadDate, loadHour){
       
        let serviceURL = this.baseURL + '/DRIVE_generateWaybill.php';
        let parameter = {   credentials : credentials, 
                            products : products, 
                            waybillConfig : {
                                loadDate : loadDate,
                                loadHour : loadHour
                            } 
                        };

        return FrameworkUtils.Http_POST(serviceURL, parameter).then(function(result){     
           return result.data;
        });
    }
    
    


}]);



