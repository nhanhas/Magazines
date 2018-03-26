app
    .controller('PurchaseModalController', ['$rootScope', '$scope','$uibModalInstance', 'StoreService', 'product', 'title', 'toRemove',  function($rootScope, $scope, $uibModalInstance, StoreService,  product, title, toRemove) {

        $scope.product = product || undefined;
        $scope.title = title || '';
        $scope.toRemove = toRemove || false;        
        $scope.termsUrl = 'https://www.drivefx.net/termos-e-condicoes';
        $scope.termsAccepted = false;
        
        $scope.loading = false;//used for onPurchase

        //Buttons
        $scope.onDismissModal = function(){
            $uibModalInstance.dismiss();
        }
        
        $scope.onCloseModal = function(result){
            $uibModalInstance.close({result: result});
        }

        //Here will be the Call to Service for purchase/upgrade
        $scope.onPurchase = function(){
            if(!product){
                //in case of error, dismiss modal with error
                $scope.onCloseModal('Error on purchase');
                return false;                
            }
            
            $scope.loading = true; //loading 

            //call STOREWS to purchase/upgrade (product and client Parameter from event)
            if(product.isCoreProduct){
                //Core Products
                StoreService.purchaseCoreProduct(product, $rootScope.systemStatus);
            }else{
                //Addons
                if(!product.toUpgrade)
                    StoreService.purchaseProduct(product, $rootScope.systemStatus);
                else
                    StoreService.upgradeProduct(product, $rootScope.systemStatus);            
            }
           

        }

        //Here will be the Call to Service for remove
        $scope.onRemovePurchase = function(){
            if(!product.isCoreProduct){
                return false;
            }

             $scope.loading = true; //loading 

             if(product.isCoreProduct){
                //Core Products
                StoreService.removeCoreProduct(product, $rootScope.systemStatus);
            }
        }

        //resize function 
        $scope.resizeNPosition = function (){
            $('head').append(
				'<style> .bs-2 .modal-body { max-height:'+( $(window).height() * 0.4 )+'px;} </style>'
			);

            $('.modal-dialog').css(
                { bottom: 220, left: 0,  right: 0, position: 'fixed'});
        }

        setTimeout(function(){ 
            $scope.resizeNPosition();
         }, 100);
        

    }]);


