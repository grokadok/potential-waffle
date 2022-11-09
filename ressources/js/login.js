class ClassLogin {
    // static el;
    constructor() {
        this.wrapper = document.getElementsByClassName("login")[0];
        this.btnController;
        this.timeout;
        this.email;
        this.currentPassword;
        this.newPassword;
        this.checkPassword;
    }
    blankBox() {
        delete this.currentPassword;
        delete this.newPassword;
        delete this.checkPassword;
        delete this.phone;
        this.confirmationText?.remove();
        delete this.confirmationText;
        let children = Array.from(this.box.children);
        for (
            let i = children.length - 1;
            i > children.indexOf(this.email.wrapper);
            i--
        )
            children[i].remove();
        this.email.input[0].focus();
    }
    async checkMail() {
        if (this.email.input[0].value && this.email.input[0].validity.valid) {
            let mailSet = await fetchPostText(
                "f=3&a=" + encodeURIComponent(this.email.input[0].value)
            );
            if (
                mailSet === "1" &&
                typeof this.currentPassword === "undefined"
            ) {
                this.blankBox();
                this.loadCurrentPW();
            } else if (
                mailSet === "0" &&
                typeof this.newPassword === "undefined"
            ) {
                this.blankBox();
                this.loadNewPW();
            }
        } else this.blankBox();
    }
    checkPWCheck() {
        if (this.checkPassword.input[0].value) {
            if (
                this.checkPassword.input[0].value ===
                this.newPassword.input[0].value
            ) {
                // this.checkPassword.input[0].className = "valid";
                this.commentDiff.classList.remove("up");
                this.removeCheckPW();
                this.loadPhone();
                this.phone.input[0].focus();
            } else {
                this.checkPassword.input[0].classList.add("invalid");
                this.commentDiff.classList.add("up");
            }
        } else {
            this.checkPassword.input[0].classList.remove("invalid");
            this.commentDiff.classList.remove("up");
        }
    }
    destroy() {}
    forgottenPW() {}
    load() {
        loadIn(this.wrapper);
        this.wrapper.innerHTML = `<div class="login-header">
            <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
                <defs>
                    <path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z">
                    </path>
                </defs>
                <g class="parallax">
                    <use href="#gentle-wave" x="48" y="0">
                    </use>
                    <use href="#gentle-wave" x="48" y="3">
                    </use>
                    <use href="#gentle-wave" x="48" y="5">
                    </use>
                    <use href="#gentle-wave" x="48" y="7">
                    </use>
                </g>
            </svg>
            <div class="login-modal">
                <div class="login-box">
                    <span class="login-title">
                    SeaDesk
                    </span>
                </div>
                <div class="login-box-buddy"></div>
            </div>
            <div class="login-footer">
                <span>2022 © SeaDesk | bopdev <span class="fullscreen">[ ]</span></span>
            </div>
        </div>`;
        this.box = this.wrapper.getElementsByClassName("login-box")[0];
        this.head = this.wrapper.getElementsByClassName("login-header")[0];
        const fullscreen = this.wrapper.getElementsByClassName("fullscreen")[0];
        this.email = new Field({
            compact: true,
            name: "Email",
            placeholder: "Addresse email",
            required: true,
            type: "email",
        });
        this.email.wrapper.classList.add("login-content");
        this.email.input[0].addEventListener("input", () => {
            login.timer();
        });
        this.email.input[0].addEventListener("focus", () => {
            login.checkMail();
        });
        this.box.appendChild(this.email.wrapper);
        this.head.addEventListener("click", (e) => {
            if (e.target !== this.box && !this.box.contains(e.target))
                themify();
        });
        fullscreen.addEventListener("click", toggleFullscreen);
        loadOut(this.wrapper);
    }
    loadCheckPW() {
        this.checkPassword = new Field({
            compact: true,
            name: "Vérification mot de passe",
            placeholder: "Vérification mot de passe",
            type: "password",
        });
        this.checkPassword.wrapper.classList.add("login-content");
        this.checkPassword.wrapper.classList.add("invalid");
        this.checkPassword.input[0].addEventListener("input", () => {
            login.checkPWCheck();
        });
        this.box.appendChild(this.checkPassword.wrapper);
        this.box.insertAdjacentHTML(
            "beforeend",
            `<div class="login-check pw-diff">
                <span>Les champs de mot de passe sont différents.</span>
            </div>`
        );
        this.commentDiff = this.box.getElementsByClassName("pw-diff")[0];
    }
    loadCurrentPW() {
        this.currentPassword = new Field({
            compact: true,
            name: "Mot de passe",
            placeholder: "Mot de passe",
            type: "current-password",
        });
        this.currentPassword.wrapper.classList.add("login-content");
        this.currentPassword.input[0].addEventListener("input", (e) => {
            login.connectBtn.disabled = e.target.value ? false : true;
        });
        this.currentPassword.input[0].addEventListener("keydown", (e) => {
            if (e.code === "Enter") ClassLogin.openWS();
        });
        this.connectBtn = document.createElement("button");
        this.connectBtn.textContent = "se connecter";
        this.connectBtn.className = "theme";
        this.connectBtn.disabled = true;
        this.connectBtn.addEventListener("click", ClassLogin.openWS);
        this.box.append(this.currentPassword.wrapper, this.connectBtn);
    }
    loadNewPW() {
        this.newPassword = new Field({
            compact: true,
            name: "Nouveau mot de passe",
            placeholder: "Nouveau mot de passe",
            type: "new-password",
        });
        this.newPassword.wrapper.classList.add("login-content");
        this.box.appendChild(this.newPassword.wrapper);
        this.box.insertAdjacentHTML(
            "beforeend",
            `<div class="login-check pw-list">
                <span>Le mot de passe doit contenir :</span>
                <ul>
                    <li>au moins une lettre minuscule</li>
                    <li>au moins une lettre majuscule</li>
                    <li>au moins un chiffre</li>
                    <li>au moins un caractère spécial parmi !@#$%^&*</li>
                    <li>au moins 12 caractères</li>
                    <li>au maximum 64 caractères</li>
                </ul>
            </div>`
        );
        this.commentList = this.box.getElementsByClassName("pw-list")[0];
        this.newPassword.input[0].addEventListener("input", () => {
            this.newPWCheck();
            if (
                this.newPassword.input[0].value &&
                this.newPassword.input[0].validity.valid &&
                typeof this.checkPassword === "undefined"
            )
                this.loadCheckPW();
            else if (
                !this.newPassword.input[0].validity.valid ||
                !this.newPassword.input[0].value
            )
                this.removeCheckPW();
            this.removePhone();
        });
        this.newPassword.input[0].addEventListener("focus", () => {
            login.newPWFocus();
        });
    }
    loadPhone() {
        this.phone = new Field({
            compact: true,
            name: "Numéro de téléphone",
            type: "phone",
        });
        this.phone.wrapper.classList.add("login-content");
        this.box.appendChild(this.phone.wrapper);
        this.box.insertAdjacentHTML(
            "beforeend",
            `<button class="theme" disabled>
                Valider
            </button>
            <div class="login-check phone-msg">
                <span>Le numéro de téléphone fourni n'est pas valide.</span>
            </div>`
        );
        this.validBtn = this.box.getElementsByTagName("button")[0];
        this.validBtn.addEventListener(
            "click",
            () => {
                login.showConfirmation();
            },
            { once: true }
        );
        this.commentPhone = this.box.getElementsByClassName("phone-msg")[0];
        this.phone.input[0].addEventListener("input", () => {
            login.phoneCheck();
        });
        this.phone.input[0].addEventListener("keydown", (e) => {
            if (e.code === "Enter" && login.phone.intlTel.isValidNumber())
                login.showConfirmation();
        });
    }
    newPWCheck() {
        // create
        const str = this.newPassword.input[0].value,
            pwSpan = this.commentList.getElementsByTagName("span")[0],
            pwList = this.commentList.getElementsByTagName("li"),
            regLower = /[a-z]+/,
            regUpper = /[A-Z]+/,
            regNumber = /[0-9]+/,
            regSpecial = /[!@#$%^&*]+/;
        let a = 0;
        if (regLower.test(str)) fadeOut(pwList[0]);
        else {
            fadeIn(pwList[0]);
            a = 1;
        }
        if (regUpper.test(str)) fadeOut(pwList[1]);
        else {
            fadeIn(pwList[1]);
            a = 1;
        }
        if (regNumber.test(str)) fadeOut(pwList[2]);
        else {
            fadeIn(pwList[2]);
            a = 1;
        }
        if (regSpecial.test(str)) fadeOut(pwList[3]);
        else {
            fadeIn(pwList[3]);
            a = 1;
        }
        if (str.length > 11) fadeOut(pwList[4]);
        else {
            fadeIn(pwList[4]);
            a = 1;
        }
        if (str.length === 64) {
            fadeIn(pwList[5]);
            a = 1;
        } else fadeOut(pwList[5]);
        a === 0 ? fadeOut(pwSpan) : fadeIn(pwSpan);
        // if testpw ok, create checkPW
        // else delete checkPW && show what's wrong
    }
    newPWFocus() {
        const input = this.newPassword.input[0];
        if (!input.validity.valid || !input.value)
            this.commentList.classList.add("up");
        input.addEventListener(
            "blur",
            () => {
                if (!login.newPassword.input[0].value)
                    login.commentList.classList.remove("up");
            },
            { once: true }
        );
    }
    static openWS() {
        login.currentPassword.input[0].blur();
        socket?.close();
        let host =
            (window.location.hostname === "localhost" ? "ws://" : "wss://") +
            window.location.host;
        socket = new WSConnection(host);
    }
    phoneCheck() {
        if (this.phone.input[0].value) {
            if (this.phone.intlTel.isValidNumber()) {
                this.commentPhone.classList.remove("up");
                this.validBtn.disabled = false;
            } else {
                this.commentPhone.classList.add("up");
                this.validBtn.disabled = true;
            }
        }
    }
    async register() {
        const email = encodeURIComponent(this.email.input[0].value.trim()),
            password = encodeURIComponent(this.newPassword.input[0].value),
            phone = encodeURIComponent(this.phone.intlTel.getNumber()),
            post =
                "f=4&email=" +
                email +
                "&phone=" +
                phone +
                "&password=" +
                password;
        let res = await fetchPostText(post);
        console.log(res);
        if (res === "1") {
            console.log("res = 1");
            // create registered message
            msg.new({
                content: `Votre compte a été créé ! Vous devriez recevoir sous peu un email ainsi qu'un sms nécéssaires à la validation de votre compte. En cas de difficulté, n'hésitez pas à contacter l'assistance.`,
                type: "success",
                btn0listener: () => {
                    login.blankBox();
                },
            });
            // const registered = document.getElementById("registered");
            // fadeOut([this.dblcheck, this.email, this.phone.wrapper]);
            // fadeIn(registered);
            // this.loginButton.className = "theme";
            // this.loginButton.textContent = "retour à l'interface de connexion";
            // this.loginButton.addEventListener("click", ClassLogin.object.load, {
            //     once: true,
            // });
        } else if (res === "0") {
            console.log("res = 0");
            msg.new({
                content:
                    "La création du compte a échouée, veuillez réessayer ultérieurement.",
                type: "warning",
            });
        }
    }
    removeCheckPW() {
        this.checkPassword?.destroy();
        delete this.checkPassword;
        this.commentDiff?.remove();
        delete this.commentDiff;
    }
    removePhone() {
        this.confirmationText?.remove();
        delete this.confirmationText;
        this.phone?.destroy();
        delete this.phone;
        this.commentPhone?.remove();
        delete this.commentPhone;
        this.validBtn?.remove();
        delete this.validBtn;
    }
    showConfirmation() {
        this.confirmationText = document.createElement("span");
        this.confirmationText.textContent = `Merci de bien vérifier les informations fournies, adresse email et numéro de téléphone portable doivent être valides et accessibles car ils seront utilisés pour finaliser la création de votre compte.`;
        this.box.insertBefore(this.confirmationText, this.email.wrapper);
        this.validBtn.textContent = "créer le compte";
        this.validBtn.addEventListener("click", () => {
            login.register();
        });
    }
    timer() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            this.checkMail();
        }, 70);
    }
}
