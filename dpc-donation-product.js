var previousQuantity = 0;
var initialCheck = true;
var initialTotal = 0;

function checkboxAction() {
    var checkbox = document.getElementById('add_donation_product');
    var quantityInput = document.getElementById('donation_product_quantity');

    if (checkbox.checked) {
        quantityInput.disabled = false;
        if (initialCheck) {
            var totalElement = document.querySelector('.order-total .woocommerce-Price-amount');
            initialTotal = parseFloat(totalElement.textContent.replace(/[^\d,.-]/g, '').replace(',', '.'));
            initialCheck = false;
        }
        updateTotal(true); // Pass true to indicate that this is an initial add
    } else {
        quantityInput.disabled = true;
        quantityInput.value = 1; // Reset quantity to 1
        previousQuantity = 0; // Reset previous quantity
        updateTotal(); // Update total immediately when checkbox is unchecked
    }
}

function updateTotal(isInitialAdd = false) {
    var checkbox = document.querySelector('input[type="checkbox"][id="add_donation_product"]');
    var quantityInput = document.getElementById('donation_product_quantity');
    var donationPrice = parseFloat(checkbox.getAttribute('data-price').replace(',', '.'));
    var totalElement = document.querySelector('.order-total .woocommerce-Price-amount');

    if (!totalElement) {
        return;
    }

    var total;

    if (!checkbox.checked) {
        total = initialTotal;
    } else {
        // Replace all non-numeric characters except for the comma and dot
        var totalText = totalElement.textContent.replace(/[^\d,.-]/g, '');

        // If there are multiple dots, replace all but the last one with empty string
        var parts = totalText.split('.');
        if (parts.length > 2) {
            totalText = parts.slice(0, -1).join('') + '.' + parts.slice(-1);
        }
        // Replace all commas with dots
        totalText = totalText.replace(',', '.');

        total = parseFloat(totalText);

        var quantity = parseInt(quantityInput.value);
        if (isInitialAdd) {
            total += donationPrice * quantity; // Add initial quantity
        } else {
            total -= donationPrice * previousQuantity; // Remove previous quantity
            total += donationPrice * quantity; // Add new quantity
        }
        previousQuantity = quantity; // Update previous quantity

        var input = document.createElement('input');
        input.setAttribute('type', 'hidden');
        input.setAttribute('name', 'add_donation_product');
        input.setAttribute('value', '1');
        document.querySelector('form.woocommerce-checkout').appendChild(input);
    }

    totalElement.textContent = wc_price_format(total, totalElement.textContent);
}

function wc_price_format(price, original) {
    // Extract currency symbol from original text
    var currencySymbol = original.match(/[^\d,.-\s]/g).join('');

    // Determine decimal separator used in original text
    var decimalSeparator = original.includes(',') && original.includes('.') && original.lastIndexOf(',') > original.lastIndex('.') ? ',' : '.';

    // Format price accordingly
    var formattedPrice = price.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    // Replace decimal separator if needed
    if (decimalSeparator === ',') {
        formattedPrice = formattedPrice.replace('.', ',');
    }

    // Place currency symbol correctly
    if (original.indexOf(currencySymbol) > 0) {
        return formattedPrice + ' ' + currencySymbol;
    } else {
        return currencySymbol + ' ' + formattedPrice;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var checkbox = document.querySelector('input[type="checkbox"][id="add_donation_product"]');
    var quantityInput = document.querySelector('input[type="number"][id="donation_product_quantity"]');

    if (checkbox) {
        checkbox.addEventListener('change', checkboxAction);
    }
    if (quantityInput) {
        quantityInput.addEventListener('input', updateTotal);
    }
});
