app
    .controller('ScreenshotModalController', ['$scope','$uibModalInstance', 'screenshotUrl',  function($scope, $uibModalInstance, screenshotUrl) {

        $scope.screenshotUrl = screenshotUrl;
    
        //Buttons
        $scope.onDismissModal = function(){
            $uibModalInstance.dismiss();
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


