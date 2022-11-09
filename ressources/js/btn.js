function buttonDown(btn) {
  btn.classList.add("btn-down");
}

function buttonUp(btn) {
  btn.classList.remove("btn-down");
}

function btnsActivate() {
  let btns = document.getElementsByTagName("button");
  for (var btn of btns) {
    btn.addEventListener("mousedown", function () {
      buttonDown(btn);
      btn.addEventListener(
        "mouseup",
        function () {
          buttonUp(btn);
        },
        { once: true }
      );
      btn.addEventListener(
        "mouseout",
        function () {
          buttonUp(btn);
          btn.removeEventListener("mousedown", function () {
            buttonDown(btn);
            btn.addEventListener(
              "mouseup",
              function () {
                buttonUp(btn);
              },
              { once: true }
            );
          });
        },
        { once: true }
      );
    });
  }
}
btnsActivate();
