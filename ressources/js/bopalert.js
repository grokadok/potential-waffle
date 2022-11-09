// const msg = document.getElementsByClassName("msg")[0];

class BopAlert {
    constructor() {
        this.wrapper = document.getElementsByClassName("msg")[0];
        this.wrapper.setAttribute("role", "alertdialog");
        this.message = this.wrapper.getElementsByTagName("span")[0];
        this.buttons = this.wrapper.getElementsByTagName("button");
        this.btn0 = this.buttons[0];
        this.btn1 = this.buttons[1];
        this.btn2 = this.buttons[2];
        this.store = [];
        this.reset();
    }
    /**
     * Closes the alert displayed, removes it from the list, and show the next one if there's one.
     */
    close() {
        // fadeOut(this.wrapper, { hide: true });
        // unblurElements(this.backgroundElements);
        if (this.wrapper.getAttribute("open") === null)
            console.warn("No open BopAlert to close.");
        else {
            this.wrapper.close();
            this.store.shift();
            this.status = undefined;
            if (this.store.length > 0) this.show(this.store[0]);
        }
    }
    /**
     * Adds an alert to the alert list, and show it if no alert in the list.
     * @param {Object} params - Settings of the bopalert.
     * @param {String} params.content - Message of the bopalert.
     * @param {String} [params.type="theme"] - Type of message: "success", "warning", "danger", defaults to neutral.
     * @param {Function} [params.btn0listener] - Optional function to add to the default behavior of btn_0.
     * @param {Function} [params.btn1listener] - Function on click of btn_1, btn disabled if not set.
     * @param {Function} [params.btn2listener] - Function on click of btn_2, btn disabled if not set.
     * @param {String} [params.btn1style="success"] - Sets the style of btn_1, accepts btn styles set in css ("theme","primary","success","info","warning","danger").
     * @param {String} [params.btn2style="info"] - Sets the style of btn_2, same as btn1style.
     * @param {String} [params.btn1text] - Text of btn_1, determines the presence of btn_1.
     * @param {String} [params.btn2text] - Text of btn_2, determines the presence of btn_2.
     */
    new(params) {
        if (params !== this.status) {
            this.store.push(params);
            if (this.wrapper.getAttribute("open") === null) {
                this.show(params);
            }
        }
    }
    /**
     * Displays an alert.
     * @param {Object} params - Alert parameters, same as this.new().
     */
    show(params) {
        this.reset();
        this.status = params;
        this.message.textContent = params.content;
        const type = params["type"] ?? "theme";
        this.wrapper.classList.add(type);
        if (!params["btn1text"]) {
            this.btn0.className = type ?? "theme";
        } else {
            this.btn0.textContent = "annuler";
            this.btn0.className = "danger";
            this.btn1.hidden = false;
            this.btn1.textContent = params["btn1text"];
            this.btn1.classList.add(params["btn1style"] ?? "success");
            if (params["btn1listener"]) {
                this.btn1.addEventListener("click", params["btn1listener"]);
            }
            if (params["btn2text"]) {
                this.btn2.hidden = false;
                this.btn2.textContent = params["btn2text"];
                this.btn2.classList.add(params["btn2style"] ?? "info");
                if (params["btn2listener"]) {
                    this.btn2.addEventListener("click", params["btn2listener"]);
                }
            }
        }
        this.btn0.addEventListener("click", function () {
            if (params.btn0listener) params.btn0listener();
            msg.close();
        });
        this.wrapper.showModal();
        elEnableTimer(Array.from(this.buttons));
        setTimeout(() => {
            this.btn0.focus();
        }, 50);
    }
    /**
     * Resets the alert element, make it ready for the next one.
     */
    reset() {
        disable(Array.from(this.buttons));
        cloneAndReplace(Array.from(this.buttons));
        this.btn0 = this.buttons[0];
        this.btn1 = this.buttons[1];
        this.btn2 = this.buttons[2];
        this.btn1.hidden = true;
        this.btn2.hidden = true;
        this.btn0.textContent = "ok";
        this.wrapper.className = "msg";
    }
}

const msg = new BopAlert();
