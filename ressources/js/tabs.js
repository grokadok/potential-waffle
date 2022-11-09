async function tabSwitch(el, index) {
  // DOM Elements
  const tabs = document.querySelectorAll(".tab"),
    tabSelectors = document.querySelectorAll(".nav-tab"),
    title = document.getElementsByClassName("title")[0],
    toolbars = document.getElementsByClassName("toolbar");
  let id = index,
    text = el.querySelector("span").textContent;

  if (tabs[id].classList.contains("fadeout")) {
    refreshTabData(tabs[id]);
    for (var i = 0; i < tabs.length; i++) {
      if (i === id) {
        fadeIn(tabs[i]);
        tabSelectors[i].classList.add("selected");
        toolbars[i].classList.remove("hide");
      } else {
        fadeOut(tabs[i]);
        tabSelectors[i].classList.remove("selected");
        toolbars[i].classList.add("hide");
      }
    }
    title.textContent = text;
  }
}
function setTabsListeners(el, index) {
  el.addEventListener("click", function () {
    tabSwitch(el, index);
  });
}
function navTabs() {
  // DOM Elements
  const tabs = document.querySelectorAll(".tab"),
    tabSelectors = document.querySelectorAll(".nav-tab");
  tabSelectors[0].classList.add("selected");
  tabSelectors.forEach(setTabsListeners);
  if (tabs.length !== tabSelectors.length) {
    console.log("Erreur : pas le même nombre de tabs que de selectors.");
  }
}
