var filter = document.getElementById('filter');
var filter_links = filter.getElementsByTagName('A');

filter.addEventListener('click', function (e) {
    if (e.target && e.target.getAttribute('data-days')) {
        if (e.preventDefault) {
            e.preventDefault();
        } else {
            e.returnValue = false;
        }

        for (var i = 0; i < filter_links.length; i++) {
            filter_links[i].className = '';
        }
        e.target.className = 'active';
        document.body.className = e.target.getAttribute('data-days');
        return false;
    }
});