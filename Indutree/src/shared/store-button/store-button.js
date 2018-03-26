/**
 * This component will be used to draw Store Generic Button
 * layout and it´s description with detailed view
 */
app
    .directive('storeButton', ['$location', function(location,) {
        return {
            restrict: 'EA',
            scope: {
                icon : '@', //a reference from fontawesome
                label : '@',
                inactive: '=?', //mark as disabled
                action : '@', //'confirm' or 'cancel' - changes the color of button
                onEnter : '&', //on click the button
                layout : '@?' // [minimal,default], will show layout as minimal (navigations) or full design
                
                         
            },
            templateUrl: 'shared/store-button/store-button.html',

            link: function (scope, element, attrs) {
                //init directive
                scope.inactive = scope.inactive || false;
                scope.onEnter = scope.onEnter || undefined;
                scope.layout = scope.layout || "default";

                scope.buttonPressed = function(){
                    if(scope.onEnter){
                        scope.onEnter();
                    }
                }


            }
        };
    }]);