app
    .controller('PublicModalController', ['$rootScope', '$scope','$uibModalInstance', 'StoreService', 'product', 'title', function($rootScope, $scope, $uibModalInstance, StoreService,  product, title) {

        $scope.product = product || undefined;
        $scope.title = title || '';     
        $scope.termsUrl = 'https://www.drivefx.net/termos-e-condicoes';
                
        $scope.loading = false;//used for onPurchase

        
        //Here will be the Call to "No, iam not Client" 
        $scope.onClickNotClient = function(){
            //Core Products
            StoreService.subscribeDrive(product, $rootScope.systemStatus); 
        }

        //Here will be the Call to "Yes, iam Client"
        $scope.onClickClient = function(){
            //Core Products
            StoreService.purchaseIntoMyDrive(product, $rootScope.systemStatus);
        }

     
        //resize function 
        $scope.resizeNPosition = function (){
            $('head').append(
				'<style> .modal-body { max-height:'+( $(window).height() * 0.4 )+'px;} </style>'
			);

            $('.modal-dialog').css(
                { bottom: 220, left: 0,  right: 0, position: 'fixed'});
        }

        setTimeout(function(){ 
            $scope.resizeNPosition();
         }, 100);
        

    }]);


