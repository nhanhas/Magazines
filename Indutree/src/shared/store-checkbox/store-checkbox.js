/**
 * This component will be used to draw Store Generic CheckBox
 * layout and it´s description with detailed view
 */
app
    .directive('storeCheckbox', ['$location', function(location,) {
        return {
            restrict: 'EA',
            scope: {                
                label : '@', 
                url : '=?', //in case of url, navigate
                model: '=' 
            },
            templateUrl: 'shared/store-checkbox/store-checkbox.html',

            link: function (scope, element, attrs) {
                //init directive
                scope.model = scope.model || undefined;
                scope.url = scope.url || '';
 
                //on switching 
                scope.onChange = function(){
                    scope.model = !scope.model;
                }

            }
        };
    }]);