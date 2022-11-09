let login = new ClassLogin(),
    socket,
    main;

class ClassMain {
    static el;
    /**
     * Loads main content from successful login's data.
     * @param {Object[]} data
     * @param {Number[]} data.chat
     * @param {Number[]} data.tabs
     */
    constructor(data) {
        ClassMain.el = this;
        main = this.wrapper;
        // parse data
        this.user = {
            attempts: data.attempts_total,
            name: data.name,
            options: data.options,
        };
        this.wrapper = document.getElementsByTagName("main")[0];
        this.chat = new BopChat(document.getElementById("chat"), data.chat);
        this.calendar = new BopCal(document.getElementById("calendar"));
        this.tabs = {
            active: data.active_tab ?? 1,
            data: data.tabs,
            item: [],
            map: data.tabs_map,
        };
        this.navbar = {
            ul: document.createElement("ul"),
            wrapper: document.getElementsByClassName("navbar")[0],
        };
        const localNavbar = localStorage.getItem("navbarClass");
        this.navbar.wrapper.className =
            "navbar " + (localNavbar !== null ? localNavbar : "left");
        if (localNavbar === null || localNavbar.split(" ").length < 2)
            this.navbar.wrapper.setAttribute(
                "style",
                localStorage.getItem("navbarStyle") ?? ""
            );
        this.topbar = {
            react: document.createElement("ul"),
            static: document.createElement("ul"),
            wrapper: document.getElementsByClassName("topbar")[0],
        };
        this.topbar.static.innerHTML = `<li>
            New
            <ul>
                <li>Email</li>
                <li>Ticket</li>
                <li>Contact</li>
                <li>Company</li>
            </ul>
            </li>
            <li>
            Search
            </li>`;
        this.topbar.wrapper.append(this.topbar.react, this.topbar.static);
        this.topbar.static
            .querySelector("ul:first-of-type li:first-of-type")
            .addEventListener("click", () => loadNewEmail());
        this.topbar.static
            .querySelector("ul:first-of-type li:nth-of-type(2)")
            .addEventListener("click", () => loadNewTicket());
        this.topbar.static
            .querySelector("ul:first-of-type li:nth-of-type(3)")
            .addEventListener("click", () => loadNewContact());
        this.topbar.static
            .querySelector("ul:first-of-type li:nth-of-type(4)")
            .addEventListener("click", () => loadNewCompany());

        // login.head.classList.add("logged");
        loadIn([
            this.wrapper,
            this.navbar.wrapper,
            this.topbar.wrapper,
            this.chat.wrapper,
            this.calendar.wrapper,
        ]);

        // setTimeout(function () {
        //     login.wrapper.className = "login hidden";
        //     // login destroy
        // }, 200);

        this.navbar.wrapper.insertAdjacentHTML(
            "beforeend",
            `<div class="nav-logo-back"></div>
            <div class="nav-logo">
                <span class="nav-title">s</span>
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
            </div>`
        );
        this.navbar.wrapper.insertBefore(
            this.navbar.ul,
            this.navbar.wrapper.children[1]
        );
        setElementDraggable(this.navbar.wrapper, {
            constrain: true,
            magnet: true,
        });
        this.navbar.wrapper
            .getElementsByClassName("nav-logo")[0]
            .addEventListener("pointerdown", (e) => {
                e.stopPropagation();
            });
        this.navbar.ul.addEventListener("pointerdown", (e) => {
            e.stopPropagation();
        });
        this.navbar.wrapper.addEventListener("pointerup", () => {
            this.navbarRelease();
        });
        // parse tabs
        this.prepareTab(this.tabs.map);
        let theme = document.createElement("li"),
            themeText = document.createElement("span"),
            logout = document.createElement("li"),
            logoutText = document.createElement("span");
        themeText.textContent = "theme";
        theme.appendChild(themeText);
        theme.insertAdjacentHTML(
            "beforeend",
            `<svg viewBox="0 0 20 20">
              <path d="M5.114,5.726c0.169,0.168,0.442,0.168,0.611,0c0.168-0.169,0.168-0.442,0-0.61L3.893,3.282c-0.168-0.168-0.442-0.168-0.61,0c-0.169,0.169-0.169,0.442,0,0.611L5.114,5.726z M3.955,10c0-0.239-0.193-0.432-0.432-0.432H0.932C0.693,9.568,0.5,9.761,0.5,10s0.193,0.432,0.432,0.432h2.591C3.761,10.432,3.955,10.239,3.955,10 M10,3.955c0.238,0,0.432-0.193,0.432-0.432v-2.59C10.432,0.693,10.238,0.5,10,0.5S9.568,0.693,9.568,0.932v2.59C9.568,3.762,9.762,3.955,10,3.955 M14.886,5.726l1.832-1.833c0.169-0.168,0.169-0.442,0-0.611c-0.169-0.168-0.442-0.168-0.61,0l-1.833,1.833c-0.169,0.168-0.169,0.441,0,0.61C14.443,5.894,14.717,5.894,14.886,5.726 M5.114,14.274l-1.832,1.833c-0.169,0.168-0.169,0.441,0,0.61c0.168,0.169,0.442,0.169,0.61,0l1.833-1.832c0.168-0.169,0.168-0.442,0-0.611C5.557,14.106,5.283,14.106,5.114,14.274 M19.068,9.568h-2.591c-0.238,0-0.433,0.193-0.433,0.432s0.194,0.432,0.433,0.432h2.591c0.238,0,0.432-0.193,0.432-0.432S19.307,9.568,19.068,9.568 M14.886,14.274c-0.169-0.168-0.442-0.168-0.611,0c-0.169,0.169-0.169,0.442,0,0.611l1.833,1.832c0.168,0.169,0.441,0.169,0.61,0s0.169-0.442,0-0.61L14.886,14.274z M10,4.818c-2.861,0-5.182,2.32-5.182,5.182c0,2.862,2.321,5.182,5.182,5.182s5.182-2.319,5.182-5.182C15.182,7.139,12.861,4.818,10,4.818M10,14.318c-2.385,0-4.318-1.934-4.318-4.318c0-2.385,1.933-4.318,4.318-4.318c2.386,0,4.318,1.933,4.318,4.318C14.318,12.385,12.386,14.318,10,14.318 M10,16.045c-0.238,0-0.432,0.193-0.432,0.433v2.591c0,0.238,0.194,0.432,0.432,0.432s0.432-0.193,0.432-0.432v-2.591C10.432,16.238,10.238,16.045,10,16.045" />
            </svg>
            <svg viewBox="0 0 20 20">
              <path d="M10.544,8.717l1.166-0.855l1.166,0.855l-0.467-1.399l1.012-0.778h-1.244L11.71,5.297l-0.466,1.244H10l1.011,0.778L10.544,8.717z M15.986,9.572l-0.467,1.244h-1.244l1.011,0.777l-0.467,1.4l1.167-0.855l1.165,0.855l-0.466-1.4l1.011-0.777h-1.244L15.986,9.572z M7.007,6.552c0-2.259,0.795-4.33,2.117-5.955C4.34,1.042,0.594,5.07,0.594,9.98c0,5.207,4.211,9.426,9.406,9.426c2.94,0,5.972-1.354,7.696-3.472c-0.289,0.026-0.987,0.044-1.283,0.044C11.219,15.979,7.007,11.759,7.007,6.552 M10,18.55c-4.715,0-8.551-3.845-8.551-8.57c0-3.783,2.407-6.999,5.842-8.131C6.549,3.295,6.152,4.911,6.152,6.552c0,5.368,4.125,9.788,9.365,10.245C13.972,17.893,11.973,18.55,10,18.55 M19.406,2.304h-1.71l-0.642-1.71l-0.642,1.71h-1.71l1.39,1.069l-0.642,1.924l1.604-1.176l1.604,1.176l-0.642-1.924L19.406,2.304z" />
          </svg>`
        );
        theme.addEventListener("click", (e) => {
            e.stopPropagation();
            this.switchTheme();
        });
        this.theme = { element: theme, options: ["light", "dark"] };
        this.theme.value = this.theme.options.indexOf(document.body.className);
        this.setThemeIcon();
        logoutText.textContent = "logout";
        logout.appendChild(logoutText);
        logout.insertAdjacentHTML(
            "beforeend",
            `<svg class="svg-icon" viewBox="0 0 20 20">
                  <polygon points="18.198,7.95 3.168,7.95 3.168,8.634 9.317,9.727 9.317,19.564 12.05,19.564 12.05,9.727 18.198,8.634 "></polygon>
                  <path d="M2.485,10.057v-3.41H2.473l0.012-4.845h1.366c0.378,0,0.683-0.306,0.683-0.683c0-0.378-0.306-0.683-0.683-0.683H1.119c-0.378,0-0.683,0.306-0.683,0.683c0,0.378,0.306,0.683,0.683,0.683h0.683v4.845C1.406,6.788,1.119,7.163,1.119,7.609v2.733c0,0.566,0.459,1.025,1.025,1.025c0.053,0,0.105-0.008,0.157-0.016l-0.499,5.481l5.9,2.733h0.931C8.634,13.266,5.234,10.458,2.485,10.057z"></path>
                  <path d="M18.169,6.584c-0.303-3.896-3.202-6.149-7.486-6.149c-4.282,0-7.183,2.252-7.484,6.149H18.169z M15.463,3.187c0.024,0.351-0.103,0.709-0.394,0.977c-0.535,0.495-1.405,0.495-1.94,0c-0.29-0.268-0.418-0.626-0.394-0.977C13.513,3.827,14.683,3.827,15.463,3.187z"></path>
                  <path d="M18.887,10.056c-2.749,0.398-6.154,3.206-6.154,9.508h0.933l5.899-2.733L18.887,10.056z"></path>
						  </svg>`
        );
        logout.addEventListener("click", (e) => {
            e.stopPropagation();
            socket.close();
        });
        this.navbar.ul.append(theme, logout);

        // populate tabs
        this.loadTab(this.tabs.active);
        // populate nav

        this.tabs.data = {};
        // hide login show main
        loadOut([
            this.wrapper,
            this.navbar.wrapper,
            this.topbar.wrapper,
            this.chat.wrapper,
            this.calendar.wrapper,
        ]);
        fadeIn(this.tabs.item[this.tabs.active].tab);
        // destroy login
        // show warning connection count
        if (data.attempts_total > 2)
            msg.new({
                content: `Bienvenue ${data.name} !
                    Pour information, ${data.attempts_total} tentatives de connexion
                    à votre compte ont échoué depuis la dernière connexion réussie.
                    Si ce nombre vous paraît anormal, vérifiez que votre compte soit bien sécurisé.`,
            });
    }
    /**
     * Resets main, navbar and topbar elements, and chat object.
     */
    static destroy() {
        const main = ClassMain.el;
        main.chat.destroy();
        main.calendar?.destroy();
        main.wrapper.innerHTML = "";
        main.navbar.wrapper.innerHTML = "";
        main.navbar.wrapper.className = "navbar left hidden loading";
        main.navbar.wrapper.style.top = "";
        main.navbar.wrapper.style.left = "";
        main.topbar.wrapper.innerHTML = "";
        main.topbar.wrapper.className = "topbar hidden loading";
        delete ClassMain.el;
    }
    loadTab(id) {
        if (this.tabs.data[id]?.fields) {
            let title = document.createElement("h1");
            title.textContent = this.tabs.data[id].name;
            this.tabs.item[id].tab.appendChild(title);
            // create fields and topbar elements
            for (const field of this.tabs.data[id].fields) {
                const el = new Field(field);
                this.tabs.item[id].tab.appendChild(el.wrapper);
                if (el.calendar) {
                    // socket.send({
                    //     // task to retrieve events
                    // })
                    el.calendar?.render();
                }
            }
            this.topbar.react.innerHTML = this.tabs.data[id].toolbar ?? "";
        } else {
            socket.send({
                f: 20,
                i: id,
            });
        }
    }
    logout() {
        // destroy everything
        // call login
    }
    /**
     * What happens when navbar is released from drag.
     */
    navbarRelease() {
        const navbarClasses = Array.from(this.navbar.wrapper.classList).filter(
            (x) => ["top", "right", "bottom", "left"].includes(x)
        );
        // store navbar settings in local
        navbarClasses.length === 1 &&
        (navbarClasses[0] === "top" || navbarClasses[0] === "bottom")
            ? localStorage.removeItem("navbarStyle")
            : localStorage.setItem(
                  "navbarStyle",
                  this.navbar.wrapper.getAttribute("style")
              );
        localStorage.setItem("navbarClass", navbarClasses.join(" "));
    }
    /**
     * Sets main element's class according to navbar position;
     */
    navbarSetMain() {
        this.wrapper.classList.remove("menu-left", "menu-right");
        if (this.navbar.wrapper.classList.contains("left")) {
            this.wrapper.classList.add("menu-left");
        } else if (this.navbar.wrapper.classList.contains("right")) {
            this.wrapper.classList.add("menu-right");
        }
    }
    parseTab(id, data) {
        this.tabs.data[id] = data;
        this.loadTab(id);
    }
    prepareTab(map, parentId = null) {
        for (const [id, children] of Object.entries(map)) {
            if (this.tabs.data[id]) {
                let newtab = document.createElement("div"),
                    newnav = document.createElement("li"),
                    navText = document.createElement("span");
                newtab.className =
                    this.tabs.active === id ? "tab" : "tab fadeout";
                navText.textContent = this.tabs.data[id].name;
                newnav.insertAdjacentHTML(
                    "beforeend",
                    this.tabs.data[id].icon.length > 0
                        ? this.tabs.data[id].icon
                        : `<svg class="svg-icon" viewBox="0 0 20 20">
                  <polygon points="18.198,7.95 3.168,7.95 3.168,8.634 9.317,9.727 9.317,19.564 12.05,19.564 12.05,9.727 18.198,8.634 "></polygon>
                  <path d="M2.485,10.057v-3.41H2.473l0.012-4.845h1.366c0.378,0,0.683-0.306,0.683-0.683c0-0.378-0.306-0.683-0.683-0.683H1.119c-0.378,0-0.683,0.306-0.683,0.683c0,0.378,0.306,0.683,0.683,0.683h0.683v4.845C1.406,6.788,1.119,7.163,1.119,7.609v2.733c0,0.566,0.459,1.025,1.025,1.025c0.053,0,0.105-0.008,0.157-0.016l-0.499,5.481l5.9,2.733h0.931C8.634,13.266,5.234,10.458,2.485,10.057z"></path>
                  <path d="M18.169,6.584c-0.303-3.896-3.202-6.149-7.486-6.149c-4.282,0-7.183,2.252-7.484,6.149H18.169z M15.463,3.187c0.024,0.351-0.103,0.709-0.394,0.977c-0.535,0.495-1.405,0.495-1.94,0c-0.29-0.268-0.418-0.626-0.394-0.977C13.513,3.827,14.683,3.827,15.463,3.187z"></path>
                  <path d="M18.887,10.056c-2.749,0.398-6.154,3.206-6.154,9.508h0.933l5.899-2.733L18.887,10.056z"></path>
						  </svg>`
                );
                newnav.appendChild(navText);

                // newnav.addEventListener("pointerdown", (e) => {
                //     e.stopPropagation();
                // });
                newnav.addEventListener("click", (e) => {
                    e.stopPropagation();
                    this.tabSwitch(id);
                });
                this.wrapper.appendChild(newtab);
                this.tabs.item[id] = { tab: newtab, li: newnav };
                if (this.tabs.data[id].actions?.length > 0) {
                    for (const value of this.tabs.data[id].actions) {
                        let newAction = document.createElement("li"),
                            text = document.createElement("span");
                        text.textContent = value.name;
                        newAction.insertAdjacentHTML(
                            "beforeend",
                            value.icon ??
                                `<svg viewBox="0 0 20 20">
							<path d="M17.645,7.262c-0.238-0.419-0.547-0.681-0.889-0.681C15.971,3.462,13.43,0.5,11.192,0.5C10.79,0.5,10.39,0.598,10,0.772C9.61,0.598,9.21,0.5,8.808,0.5c-2.238,0-4.779,2.962-5.564,6.08c-0.342,0-0.651,0.262-0.889,0.681C1.302,7.294,0.542,8.415,0.542,9.958c0,1.566,0.781,2.702,1.858,2.702c0.212,0,0.409-0.056,0.594-0.139c0.478,1.431,1.355,1.868,1.939,1.997v2.195c0,0.187,0.151,0.338,0.338,0.338c0.187,0,0.338-0.151,0.338-0.338v-0.778c0.488,0.874,1.471,1.566,2.702,1.903v0.564c0,0.187,0.151,0.338,0.338,0.338s0.338-0.151,0.338-0.338v-0.418c0.22,0.034,0.446,0.056,0.676,0.068v1.026c0,0.187,0.151,0.338,0.338,0.338s0.338-0.151,0.338-0.338v-1.026c0.23-0.012,0.456-0.033,0.676-0.068v0.418c0,0.187,0.151,0.338,0.338,0.338s0.338-0.151,0.338-0.338V17.84c1.232-0.337,2.215-1.029,2.702-1.903v0.778c0,0.187,0.151,0.338,0.338,0.338s0.338-0.151,0.338-0.338v-2.195c0.587-0.131,1.462-0.569,1.939-1.997c0.186,0.083,0.382,0.139,0.594,0.139c1.077,0,1.858-1.137,1.858-2.702C19.458,8.415,18.698,7.294,17.645,7.262z M2.4,11.647c-0.466,0-0.844-0.756-0.844-1.689c0-0.558,0.137-1.049,0.346-1.357c0.487,0.122,1.083,0.582,1.276,2.018C3.048,11.224,2.749,11.647,2.4,11.647z M12.094,7.98c0.171-0.171,0.737,0.119,1.264,0.647c0.528,0.528,0.817,1.094,0.647,1.264c-0.171,0.171-0.737-0.119-1.264-0.647C12.213,8.717,11.923,8.151,12.094,7.98z M6.66,8.627C7.188,8.099,7.754,7.81,7.924,7.98c0.171,0.171-0.119,0.737-0.647,1.264C6.75,9.772,6.184,10.062,6.013,9.891C5.843,9.721,6.132,9.155,6.66,8.627z M14.701,13.216c-0.04,0.005-0.08,0.008-0.12,0.008c-0.76,0-1.409-0.939-1.484-1.051c-1.236-1.855-3.078-1.876-3.097-1.876c-0.075,0.001-1.869,0.034-3.097,1.876c-0.079,0.118-0.798,1.144-1.604,1.043c-0.451-0.061-0.796-0.439-1.025-1.124l0.641-0.214c0.134,0.402,0.306,0.645,0.472,0.668c0.286,0.041,0.735-0.424,0.953-0.749C7.776,9.646,9.91,9.621,10,9.621s2.224,0.025,3.659,2.177c0.218,0.325,0.683,0.789,0.953,0.748c0.166-0.022,0.338-0.266,0.472-0.668l0.641,0.214C15.497,12.777,15.152,13.155,14.701,13.216z M17.6,11.647c-0.349,0-0.649-0.424-0.777-1.028c0.193-1.435,0.789-1.895,1.276-2.018c0.209,0.308,0.346,0.798,0.346,1.357C18.445,10.891,18.067,11.647,17.6,11.647z"></path>
						                </svg>`
                        );
                        newAction.appendChild(text);
                        newAction.addEventListener("pointerdown", (e) => {
                            e.stopPropagation();
                        });
                        newAction.addEventListener("click", (e) => {
                            e.stopPropagation();
                            eval(value.action);
                        });
                        if (!this.tabs.item[id].ul) {
                            const ul = document.createElement("ul");
                            newnav.appendChild(ul);
                            this.tabs.item[id].ul = ul;
                        }
                        this.tabs.item[id].ul.appendChild(newAction);
                    }
                }
                if (parentId) {
                    const parent = this.tabs.item[parentId];
                    if (!parent.ul) {
                        const ul = document.createElement("ul");
                        parent.li.appendChild(ul);
                        parent.ul = ul;
                    }
                    parent.ul.appendChild(newnav);
                } else this.navbar.ul.appendChild(newnav);
                if (typeof children === "object") {
                    this.prepareTab(children, id);
                }
            }
        }
    }
    setThemeIcon() {
        for (let [key, value] of this.theme.options.entries()) {
            const svg = this.theme.element.getElementsByTagName("svg")[key];
            key === this.theme.value
                ? svg.classList.remove("hide")
                : svg.classList.add("hide");
        }
    }
    switchTheme() {
        // set theme.value to next option if exist, else 0
        this.theme.value =
            this.theme.value < this.theme.options.length - 1
                ? this.theme.value + 1
                : 0;
        const theme = this.theme.options[this.theme.value];
        this.setThemeIcon();
        document.body.className = theme;
        localStorage.setItem("theme", theme);
    }
    tabSwitch(id) {
        // if tab not loaded, loadTab(id)
        if (this.tabs.item[id].tab.children.length === 0) this.loadTab(id);
        else {
            // this.title.textContent = this.tabs.data[id].name;
            this.topbar.react.innerHTML = this.tabs.data[id]?.toolbar ?? "";
        }
        localStorage.setItem("active_tab", id);
        fadeOut(this.tabs.item[this.tabs.active].tab);
        this.tabs.item[this.tabs.active].li.classList.remove("active");
        fadeIn(this.tabs.item[id].tab);
        this.tabs.item[id].li.classList.add("active");
        this.tabs.active = id;
    }
}

themifyCache();

login.load();

// Onload
window.onload = document.body.classList.remove("loading");
