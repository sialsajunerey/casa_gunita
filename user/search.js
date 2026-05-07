// Search functionality for navbar
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('navSearch');
    const resultsDropdown = document.getElementById('searchResults');
    let searchTimeout;

    if (!searchInput) return;

    // Handle search input
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        // Clear timeout if exists
        clearTimeout(searchTimeout);

        if (query.length < 1) {
            resultsDropdown.classList.remove('open');
            resultsDropdown.innerHTML = '';
            return;
        }

        // Debounce the search
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-search-wrap')) {
            resultsDropdown.classList.remove('open');
        }
    });

    // Prevent dropdown from closing when clicking inside
    resultsDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    function performSearch(query) {
        fetch('search_api.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(products => {
                displayResults(products);
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsDropdown.classList.remove('open');
            });
    }

    function displayResults(products) {
        if (products.length === 0) {
            resultsDropdown.innerHTML = '<div class="search-result-empty">No items found</div>';
            resultsDropdown.classList.add('open');
            return;
        }

        const html = products.map(product => `
            <a href="customize.php?product_id=${product.product_id}" class="search-result-item">
                <div class="search-result-image ${!product.image ? 'no-image' : ''}">
                    ${product.image ? 
                        `<img src="../assets/images/${product.image}" alt="${product.name}" onerror="this.style.display='none';">` 
                        : '<span>📷</span>'
                    }
                </div>
                <div class="search-result-content">
                    <div class="search-result-name">${product.name}</div>
                    <div class="search-result-price">${product.formattedPrice}</div>
                </div>
            </a>
        `).join('');

        resultsDropdown.innerHTML = html;
        resultsDropdown.classList.add('open');
    }
});
