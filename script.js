document.getElementById("search").addEventListener("keyup", function () {
  let value = this.value.toLowerCase();
  let items = document.querySelectorAll(".item");

  items.forEach((item) => {
    item.style.display = item.textContent.toLowerCase().includes(value)
      ? "block"
      : "none";
  });
});
