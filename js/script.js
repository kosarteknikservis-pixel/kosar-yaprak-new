// Aşağıdaki kod parçasını kaldırın veya yorum satırına alın:

document.addEventListener('DOMContentLoaded', function() {
    const products = document.querySelectorAll('.product');
    products.forEach(function(product) {
        product.addEventListener('click', function() {
            // Bu alert kodu uyarı mesajını gösterir, bunu kaldırabilirsiniz:
            // alert('Bir ürüne tıkladınız!');
        });
    });
});
