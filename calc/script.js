function calculate(){
    let countI=document.querySelector(".count");
    let count=parseFloat(countI.value)
    let productI=document.querySelector("select[name='products']")
    let product=parseFloat(productI.value)

    if (isNaN(count) || count<=0){
        alert("Введите натуральное число")
        return;
    }

    let mult=count*product
    let res=document.querySelector('.result')

    res.innerHTML=`<p>Стоимость товаров равна: ${mult}</p>`
    
}