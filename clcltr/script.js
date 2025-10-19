const prices = {
  type1: 1500,
  type2: 2500,
  type3: 3000,
};

const optionPrices = {
  opt1: 500,
  opt2: 1000,
};

const checkPrice = 300;

const countI = document.getElementById("count");
const radios = document.querySelectorAll('input[name="serviceType"]');
const optionsS = document.getElementById("options");
const optionsL = document.getElementById("optionsL");
const checkbox = document.getElementById("check");
const checkL = document.getElementById("checkL");
const result = document.getElementById("result");

function f() {
  const selectedType =
    document.querySelector('input[name="serviceType"]:checked')?.value ||
    "type1";

  optionsL.style.display = "none";
  optionsS.style.display = "none";
  checkL.style.display = "none";

  optionsS.value = "opt1"; // Устанавливаем значение по умолчанию
  checkbox.checked = false;

  if (selectedType === "type2") {
    optionsL.style.display = "block";
    optionsS.style.display = "block";
  } else if (selectedType === "type3") {
    checkL.style.display = "block";
  }

  calc();
}

function calc() {
  const selectedType =
    document.querySelector('input[name="serviceType"]:checked')?.value ||
    "type1";
  let basePrice = prices[selectedType] || 0;

  let optionPrice = 0;
  if (optionsS.style.display !== "none") {
    const selectedOption = optionsS.value;
    optionPrice = optionPrices[selectedOption] || 0;
  }

  let checkAddon = 0;
  if (checkL.style.display !== "none" && checkbox.checked) {
    checkAddon = checkPrice;
  }

  const count = parseInt(countI.value, 10) || 1;

  const total = (basePrice + optionPrice + checkAddon) * count;

  result.textContent = `Итог: ${total} руб.`;
}

window.addEventListener("DOMContentLoaded", () => {
  countI.addEventListener("input", () => {
    let count = parseInt(countI.value, 10);
    if (isNaN(count) || count < 1) {
      count = 1;
      countI.value = 1;
    }
    calc();
  });

  radios.forEach((radio) => {
    radio.addEventListener("change", f);
  });

  optionsS.addEventListener("change", calc);
  checkbox.addEventListener("change", calc);

  f();
  calc();
});
