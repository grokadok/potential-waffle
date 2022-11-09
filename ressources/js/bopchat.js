/**
 * Creates tabbed websocket chats in specified wrapper.
 */
class BopChat {
    static chats = [];
    /**
     * @param {HTMLElement} element - The element designed to contain the bopchat.
     * @param {Number[]} [idchat] - Optionnal array of chat ids to be opened at bopchat creation. Others may be added later.
     */
    constructor(element, idchat) {
        BopChat.chats.push(this);
        this.id = BopChat.chats.indexOf(this);
        this.active = 0;
        this.chats = [];
        this.wrapper = element;
        this.header = document.createElement("div");
        this.chatTabs = document.createElement("div");
        this.content = document.createElement("div");
        this.users = document.createElement("ul");
        this.footer = document.createElement("div");
        this.input = document.createElement("textarea");
        this.inviteButton = document.createElement("button");
        this.sendButton = document.createElement("button");
        this.typingToggle = 0;
        this.usersButton = document.createElement("button");

        if (element.id === "chat") {
            this.chatButton = document.createElement("button");
            this.emailButton = document.createElement("button");
            this.minmaxButton = document.createElement("button");
            this.searchButton = document.createElement("button");
            this.ul = document.createElement("ul");
            this.searchTab = document.createElement("div");
            this.searchTab.className = "search fadeout";
            this.searchTab.hidden = true;
            this.searchTab.textContent = `Vous pouvez utiliser la zone d'écriture ci dessous pour chercher un utilisateur avec qui ouvrir un chat.`;
            this.searchTab.setAttribute("data-i", 0);
            this.content.append(this.searchTab);
            this.footer.append(this.ul);
            this.chatButton.textContent = "📟";
            this.chatButton.addEventListener("click", () => {
                this.chatButton.blur();
                this.wrapper.classList.toggle("toggle");
                if (
                    this.wrapper.classList.contains("max") &&
                    !this.wrapper.classList.contains("toggle")
                ) {
                    this.minmaxButton.classList.remove("btn-down");
                    this.wrapper.classList.remove("max");
                }
            });
            this.emailButton.textContent = "📠";
            this.emailButton.addEventListener("click", () => {
                this.writeEmail();
            });
            this.minmaxButton.textContent = "▫︎◻︎";
            this.minmaxButton.addEventListener("click", () => {
                this.minmaxButton.blur();
                this.wrapper.classList.toggle("max");
                this.wrapper.classList.contains("max")
                    ? this.minmaxButton.classList.add("btn-down")
                    : this.minmaxButton.classList.remove("btn-down");
            });
            this.searchButton.textContent = "🔎";
            this.searchButton.setAttribute("data-i", 0);
            this.searchButton.addEventListener("click", () => {
                this.searchButton.blur();
                this.showTab(this.searchTab);
            });
            this.header.append(
                this.chatButton,
                this.minmaxButton,
                this.searchButton
            );
            this.wrapper.classList.add("user-min");
        }
        this.chatTabs.setAttribute("tabindex", "-1");
        this.usersButton.textContent = "👤";
        this.usersButton.addEventListener("click", () => {
            this.usersButton.blur();
            toggleClasses(this.wrapper, {
                classes: ["user-min", "user-max"],
                none: true,
            });
        });
        this.header.append(this.chatTabs, this.usersButton);
        setElementAttributes(this.input, {
            "data-f": "7",
            "data-s": "8",
            rows: "1",
        });
        this.input.addEventListener("input", () => {
            if (
                this.active > 0 &&
                !this.inviteButton.classList.contains("btn-down")
            ) {
                this.typing();
            } else if (
                this.active === 0 ||
                this.inviteButton.classList.contains("btn-down")
            ) {
                if (this.input.value) {
                    socket.send({
                        c: this.active > 0 ? this.active : null,
                        f: 7,
                        i: this.input.value,
                        s: 8,
                        x: 0,
                        // z: this.id,
                    });
                } else {
                    fadeOut(this.ul);
                }
            }
            this.input.style.height = "auto";
            this.input.style.height = this.input.scrollHeight + "px";
        });
        this.input.addEventListener("keydown", (e) => {
            switch (e.code) {
                case "Enter":
                    e.preventDefault();
                    if (e.shiftKey) {
                        this.input.value += "\n";
                    } else if (this.input.value) this.validate();
                    break;
                case "ArrowUp":
                    if (
                        !this.ul.classList.contains("fadeout") &&
                        this.ul.children.length > 0
                    ) {
                        this.ul.children[0].focus();
                    }
                    break;
                default:
                    break;
            }
        });
        this.inviteButton.textContent = "+";
        this.inviteButton.addEventListener("click", () => {
            this.inviteButton.blur();
            this.inviteButton.classList.toggle("btn-down");
            if (this.inviteButton.classList.contains("btn-down")) {
                this.input.focus();
                fadeOut(this.sendButton);
                if (this.input.value)
                    socket.send({
                        c: this.active > 0 ? this.active : null,
                        f: 7,
                        i: this.input.value,
                        s: 8,
                        x: 0,
                        // z: this.id,
                    });
            } else if (!this.inviteButton.classList.contains("btn-down"))
                fadeIn(this.sendButton);
            fadeOut(this.ul);
        });
        this.sendButton.textContent = "➤";
        // fadeOut([this.inviteButton, this.sendButton], { hide: true });
        this.sendButton.addEventListener("click", () => {
            this.validate();
        });
        // this.footer.insertBefore(this.input, this.footer.firstElementChild);
        this.footer.append(this.inviteButton, this.input, this.sendButton);
        // this.footer.append(this.sendButton);
        this.wrapper.append(this.header, this.content, this.users, this.footer);
        this.wrapper.classList.add("bopchat");
        if (idchat.length > 0) {
            for (const id of idchat)
                socket.send({
                    f: 14,
                    i: id,
                    // z: this.id,
                });
        } else {
            // show searchTab.
            // this.searchTab.className = "search";
            this.showTab(this.searchTab);
            // set input as search field for users.
        }
    }
    /**
     * Adds a chat channel.
     * @param {Object} params
     * @param {Number} params.id
     * @param {String} params.name
     * @param {Object[]} params.content
     * @param {Object[]} params.participants
     */
    addChat(params) {
        let tabButton, tab, users;
        // if chat present, empty tab
        if (this.chats.includes(params.id)) {
            tab = this.content.querySelector(`[data-i="${params.id}"]`);
            users = tab.getElementsByTagName("datalist")[0];
            tabButton = this.chatTabs.querySelector(`[data-i="${params.id}"]`);
            tab.innerHTML = "";
            removeOptions(users, true);
        } else {
            // else add chat
            this.chats.push(params.id);
            tabButton = document.createElement("button");
            tab = document.createElement("div");
            tab.setAttribute("data-i", params.id);
            users = document.createElement("datalist");
            tabButton.textContent = params.name;
            tabButton.setAttribute("data-i", params.id);
            this.chatTabs.append(tabButton);
            this.content.append(tab);
            tabButton.addEventListener("click", () => {
                tabButton.blur();
                if (this.active !== params.id) {
                    this.showTab(tab);
                } else {
                    msg.new({
                        content: `Souhaitez-vous fermer l'onglet ${params.name} ?`,
                        btn1text: "Fermer",
                        btn1listener: () => {
                            socket.send({
                                f: 19,
                                i: params.id,
                            });
                            // this.remove(params.id);
                            msg.close();
                        },
                    });
                }
            }); // show tab hide others
        }
        //refresh users data
        for (const user of params.participants) {
            let option = document.createElement("option");
            option.value = user.name;
            setElementAttributes(option, {
                "data-id": user.iduser,
                "data-position": user.position,
                "data-avatar":
                    user.image ??
                    user.name
                        .split(" ")
                        .map((x) => x.substring(1, 0))
                        .join("")
                        .toUpperCase(),
                "data-status": user.status,
            });
            users.append(option);
        }
        tab.append(users);
        // refresh chat data
        for (const message of params.content) {
            this.addMessage(message);
        }
        this.showTab(tab);
        // this.expand();
    }
    /**
     * Adds a message to a chat channel.
     * @param {Object} params
     * @param {String} params.content - Message content
     * @param {Number} params.idchat - Chat id
     * @param {Number} params.iduser - User id
     * @param {String} [params.img] - Image url
     * @param {String} params.name - User name
     * @param {Number[]} params.readby
     * @param {String} params.time - Message time
     */
    addMessage(params) {
        let tab = this.content.querySelector(`[data-i="${params.idchat}"]`);
        if (tab) {
            let field = document.createElement("fieldset"),
                avatar = document.createElement("div"),
                user = tab.querySelector(`[data-id="${params.iduser}"]`),
                legend = document.createElement("legend"),
                content = document.createElement("span"),
                timestamp = document.createElement("span"),
                separator;
            const separators = Array.from(tab.querySelectorAll("button")),
                time = new Date(params.created * 1000),
                now = new Date(),
                period = now.getTime() - time.getTime(),
                day = 24 * 60 * 60 * 1000,
                week = 7 * day;

            setElementAttributes(field, {
                "data-t": params.created,
                "data-i": params.iduser,
                "data-readby": params.readby,
            });
            field.className = user.getAttribute("data-position");
            legend.textContent = user.value;
            content.textContent = params.content;
            const dataAvatar = user.getAttribute("data-avatar");
            dataAvatar.substring(0, 1) === "/"
                ? (avatar.style.backgroundImage = dataAvatar)
                : (avatar.textContent = dataAvatar);
            field.append(legend, timestamp, avatar, content);

            // separator
            timestamp.textContent = time.toLocaleTimeString(
                window.navigator.language,
                {
                    hour: "2-digit",
                    minute: "2-digit",
                }
            );
            if (
                time.toLocaleDateString() === now.toLocaleDateString() &&
                !separators.find((el) => el.textContent === "Aujourd'hui")
            ) {
                separator = document.createElement("button");
                separator.className = "theme";
                separator.textContent = "Aujourd'hui";
                tab.insertBefore(separator, tab.firstChild);
                // get index of separator in tab, foreach div before index
                separator.addEventListener("click", () => {
                    separator.blur();
                    const sepIndex = Array.from(tab.children).indexOf(
                        separator
                    );
                    for (let i = 0; i < sepIndex; i++) {
                        tab.children[i].classList.toggle("fadeout");
                    }
                });
            } else if (
                time.toLocaleDateString() !== now.toLocaleDateString() &&
                period < week &&
                !separators.find(
                    (el) =>
                        el.textContent ===
                        capitalize(
                            time.toLocaleString(window.navigator.language, {
                                weekday: "long",
                                day: "numeric",
                            })
                        )
                )
            ) {
                separator = document.createElement("button");
                separator.className = "theme";
                separator.textContent = capitalize(
                    time.toLocaleString(window.navigator.language, {
                        weekday: "long",
                        day: "numeric",
                    })
                );
                tab.insertBefore(separator, tab.firstChild);
                separator.addEventListener("click", () => {
                    separator.blur();
                    const sepIndex = Array.from(tab.children).indexOf(
                        separator
                    );
                    for (let i = sepIndex - 1; i >= 0; i--) {
                        if (
                            tab.children[i].tagName.toLowerCase() === "fieldset"
                        )
                            tab.children[i].classList.toggle("fadeout");
                        else break;
                    }
                });
            } else if (
                period > week &&
                !separators.find(
                    (el) =>
                        el.textContent ===
                        time.toLocaleDateString(window.navigator.language, {
                            weekday: "long",
                            day: "numeric",
                            month: "long",
                            year: "numeric",
                        })
                )
            ) {
                separator = document.createElement("button");
                separator.className = "theme";
                separator.textContent = time.toLocaleString(
                    window.navigator.language,
                    {
                        weekday: "long",
                        day: "numeric",
                        month: "long",
                        year: "numeric",
                    }
                );
                tab.insertBefore(separator, tab.firstChild);
                separator.addEventListener("click", () => {
                    separator.blur();
                    const sepIndex = Array.from(tab.children).indexOf(
                        separator
                    );
                    for (let i = sepIndex - 1; i >= 0; i--) {
                        if (
                            tab.children[i].tagName.toLowerCase() === "fieldset"
                        )
                            tab.children[i].classList.toggle("fadeout");
                        else break;
                    }
                });
            }
            tab.insertBefore(field, tab.firstChild);
            if (field.className === "user") {
                tab.scrollTop = tab.scrollHeight;
            } else {
                tab.scrollTop = tab.scrollHeight; // replace with button appearing and blinking on new unread message to scroll to bottom
            }
        }
    }
    /**
     * Adds a user to channel
     */
    addUser(params) {
        userId = params.id;
        userName = params.name;
        userImg = params.img;
        if (userImg) {
            // avatar=img??first name's letter and color background
            avatar.style.backgroundImage = userImg;
        } else {
            // check if other avatar with same letters, remove used color(s) if so
            // get random pastel color in the remaining ones
            avatar.style.backgroundColor = color;
            avatar.textContent = userName
                .split(" ")
                .map((x) => x.substring(1, 0))
                .join("");
        }
    }
    collapse() {
        this.wrapper.classList.remove("toggle", "max");
    }
    /**
     * Reset chat element for logout.
     */
    destroy() {
        this.wrapper.innerHTML = "";
        this.wrapper.className = "loading hidden";
        BopChat.chats.splice(BopChat.chats.indexOf(this), 1);
    }
    static destroyAll() {
        for (const chat of BopChat.chats) chat.destroy();
    }
    expand() {
        this.wrapper.classList.add("toggle");
    }
    /**
     * Maximize chat window
     */
    maximize() {
        this.wrapper.classList.add("max");
    }
    /**
     * Minimize chat window
     */
    minimize() {
        this.wrapper.classList.remove("max");
    }
    /**
     * Parse users for search input.
     * @param {Object} data
     */
    parseData(data) {
        const el = this.input,
            ul = this.ul;
        if (data.response[0]) {
            if (data.response[0].fail) {
                msg.new({
                    content: data.response[0].fail,
                    btn0listener: () => el.focus(),
                });
                el.value = el.value.trim();
            } else if (data.response[0].content) {
                let ulList = [];
                removeChildren(ul, true);
                for (const obj of data.response) {
                    const value = `${obj.id}`;
                    if (obj.id !== null && !ulList.includes(value)) {
                        let li = document.createElement("li"),
                            span = document.createElement("span");
                        li.setAttribute("data-select", value);
                        li.setAttribute("tabindex", "0");
                        span.textContent = obj.content;
                        li.append(span);
                        if (obj.secondary) {
                            let span = document.createElement("span");
                            span.textContent = `(${obj.secondary})`;
                            li.append(span);
                        }
                        if (obj.role) {
                            const roles = obj.role.split(",");
                            for (const role of roles) {
                                let btn = document.createElement("button");
                                btn.textContent = role;
                                btn.disabled = true;
                                li.append(btn);
                            }
                        }
                        if (obj.email) {
                            li.setAttribute("data-email", obj.email);
                            let email = document.createElement("span");
                            email.textContent = `(${obj.email})`;
                            span.insertAdjacentElement("afterend", email);
                        }
                        if (
                            (obj.status && obj.status === 1) ||
                            (obj.inchat && obj.inchat === 1)
                        ) {
                            li.classList.add("offline");
                        }
                        ul.append(li);
                    }
                }
                highlightSearch(
                    Array.from(ul.getElementsByTagName("span")),
                    el.value.split(" ")
                );
                for (const child of ul.children) {
                    child.addEventListener("keydown", (e) =>
                        this.selectizeKeysNav(e)
                    );
                    child.addEventListener("click", (e) => {
                        this.request([
                            parseInt(
                                e.currentTarget.getAttribute("data-select")
                            ),
                        ]);
                        this.input.value = "";
                        this.inviteButton.classList.remove("btn-down");
                        fadeIn(this.sendButton);
                        fadeOut(this.ul);
                    });
                }
                ul.children.length > 0
                    ? fadeIn(ul, {
                          dropdown: ul.closest("fieldset"),
                      })
                    : fadeOut(ul);
            } else fadeOut(ul);
        } else fadeOut(ul);
    }
    /**
     * Asks for chat data to server.
     * @param {Number} id - idchat
     */
    refresh(id) {
        socket.send({
            f: 14,
            i: id,
            // z: this.id,
        });
    }
    /**
     * Removes a chat channel.
     * @param {Number} id - idchat to be removed.
     */
    remove(id) {
        // send server intel that user left chat.
        // socket.send({
        //     f: 19,
        //     i: id,
        // });
        const tabToClose = this.content.querySelector(`[data-i='${id}']`),
            chatTabToClose = this.chatTabs.querySelector(`[data-i='${id}']`);
        if (this.active === id) this.showTab(tabToClose.previousElementSibling);
        tabToClose.remove();
        chatTabToClose.remove();
        this.chats.splice(this.chats.indexOf(id), 1);
    }
    /**
     * Chat request user(s).
     * @param {Number[]} users - iduser of users to send request.
     */
    request(users) {
        socket.send({
            f: 17,
            i: users,
            c: this.active > 0 ? this.active : undefined,
            // z: this.id,
        });
    }
    selectizeKeysNav(e) {
        // modifier en function pour la navigation globale dans le site, avec raccourcis clavier (n= jump to navbar, t=jump to topbar, ...)
        switch (e.code) {
            case "ArrowUp":
                e.preventDefault();
                e.currentTarget.nextSibling?.focus();
                break;
            case "ArrowDown":
                e.preventDefault();
                e.currentTarget.previousSibling
                    ? e.currentTarget.previousSibling.focus()
                    : this.input[0].focus();
                break;
            case "Enter":
            case "Space":
                if (!e.currentTarget.classList.contains("offline")) {
                    this.request([
                        parseInt(e.currentTarget.getAttribute("data-select")),
                    ]);
                    this.input.value = "";
                    this.inviteButton.classList.remove("btn-down");
                    fadeIn(this.sendButton);
                    fadeOut(this.ul);
                }
                break;
            case "Escape":
                e.preventDefault();
                fadeOut(this.ul);
                this.input[0].blur();
        }
    }
    /**
     * Send chat message to server (with idchat, server already knows iduser)
     * @param {JSON} chat
     * @param {Number} chat.id
     * @param {String} chat.message
     */
    send(chat) {
        socket.send({
            i: chat.id,
            m: chat.message,
            f: 16,
            // z: this.id,
        });
        this.input.disabled = true;
    }
    /**
     * Hides all tabs, shows the one in param.
     * @param {HTMLElement} tab
     */
    showTab(tab) {
        const id = parseInt(tab.getAttribute("data-i"));
        if (id !== this.active) {
            let otherTabs = Array.from(this.content.children);
            otherTabs.splice(otherTabs.indexOf(tab), 1);
            fadeOut(otherTabs);
            this.active > 0
                ? this.chatTabs
                      .querySelector(`[data-i='${this.active}']`)
                      .classList.remove("btn-down")
                : this.header.classList.remove("search");
            if (this.inviteButton.classList.contains("btn-down")) {
                fadeIn(this.sendButton);
                this.inviteButton.classList.remove("btn-down");
            }
            // if chat tab
            if (id > 0) {
                fadeOut(this.ul);
                fadeIn([this.inviteButton, this.sendButton]);
                this.users.innerHTML = "";
                for (const user of Array.from(
                    tab.getElementsByTagName("datalist")[0].children
                )) {
                    if (parseInt(user.getAttribute("data-status")) < 2) {
                        let li = document.createElement("li"),
                            name = document.createElement("div"),
                            avatar = document.createElement("div");
                        name.textContent = user.value;
                        li.setAttribute(
                            "data-id",
                            user.getAttribute("data-id")
                        );
                        li.setAttribute(
                            "data-position",
                            user.getAttribute("data-position")
                        );
                        li.className = user.getAttribute("data-position");
                        if (parseInt(user.getAttribute("data-status")) === 1) {
                            li.classList.add("offline");
                        }
                        const dataAvatar = user.getAttribute("data-avatar");
                        dataAvatar.substring(0, 1) === "/"
                            ? (avatar.style.backgroundImage = dataAvatar)
                            : (avatar.textContent = dataAvatar);
                        li.append(avatar, name);
                        this.users.append(li);
                        fadeIn([this.usersButton, this.users]);
                    }
                }
            } else {
                this.users.innerHTML = "";
                fadeOut([
                    this.inviteButton,
                    this.sendButton,
                    this.usersButton,
                    this.users,
                ]);
            }
            fadeIn(tab);
            this.input.focus();
            this.active = id;
            this.active > 0
                ? this.chatTabs
                      .querySelector(`[data-i='${this.active}']`)
                      .classList.add("btn-down")
                : this.header.classList.add("search");
        }
    }
    /**
     * Sends info to server that user is typing in chat.
     */
    async typing() {
        if (this.typingToggle === 0 && this.input.value) {
            socket.send({
                f: 15,
                i: this.active,
                // z: this.id,
            });
            this.typingToggle = 1;
            this.typingTimeout = setTimeout(() => {
                this.typingToggle = 0;
            }, 5000);
        }
    }
    updateUsers(chat) {
        if (this.content.querySelector(`[data-i='${chat.id}']`)) {
            // let's update user datalist first
            let userList = [],
                datalist = this.content
                    .querySelector(`[data-i='${chat.id}']`)
                    .getElementsByTagName("datalist")[0];
            datalist.innerHTML = "";
            for (const user of chat.participants) {
                let option = document.createElement("option");
                option.value = user.name;
                setElementAttributes(option, {
                    "data-id": user.iduser,
                    "data-position": user.position,
                    "data-avatar":
                        user.image ??
                        user.name
                            .split(" ")
                            .map((x) => x.substring(1, 0))
                            .join("")
                            .toUpperCase(),
                    "data-status": user.status,
                });
                userList.push(user.iduser);
                datalist.append(option);
            }
            // then compare datalist with user list if chat active
            if (this.active === parseInt(chat.id)) {
                let ullist = [],
                    ul = this.users.children;
                for (let child of ul) {
                    ullist.push(parseInt(child.getAttribute("data-id")));
                }
                // remove/add/update user list
                const compare = arrayCompare(userList, ullist);
                for (const dataId of compare.only_1) {
                    const user = datalist.querySelector(
                        `[data-id='${dataId}']`
                    );
                    if (parseInt(user.getAttribute("data-status")) < 2) {
                        let li = document.createElement("li"),
                            name = document.createElement("div"),
                            avatar = document.createElement("div");
                        name.textContent = user.value;
                        li.setAttribute("data-id", dataId);
                        li.setAttribute(
                            "data-position",
                            user.getAttribute("data-position")
                        );
                        li.className =
                            user.getAttribute("data-position") + " fadeout";
                        if (parseInt(user.getAttribute("data-status")) === 1) {
                            li.classList.add("offline");
                        }
                        const dataAvatar = user.getAttribute("data-avatar");
                        dataAvatar.substring(0, 1) === "/"
                            ? (avatar.style.backgroundImage = dataAvatar)
                            : (avatar.textContent = dataAvatar);
                        li.append(avatar, name);
                        this.users.append(li);
                        fadeIn(li);
                    }
                }
                for (const dataId of compare.only_2) {
                    el = this.users.querySelector(`[data-id='${dataId}']`);
                    el.remove();
                }
                for (const dataId of compare.both) {
                    const status = parseInt(
                        datalist
                            .querySelector(`[data-id='${dataId}']`)
                            .getAttribute("data-status")
                    );
                    switch (status) {
                        case 0:
                            this.users
                                .querySelector(`[data-id='${dataId}']`)
                                .classList.remove("offline");
                            break;
                        case 1:
                            this.users
                                .querySelector(`[data-id='${dataId}']`)
                                .classList.add("offline");
                            break;
                        case 2:
                            this.users
                                .querySelector(`[data-id='${dataId}']`)
                                .remove();
                            break;
                    }
                    // if (status === 1)
                    //     this.users
                    //         .querySelector(`[data-id='${dataId}']`)
                    //         .classList.add("offline");
                    // else if (status === 0)
                    //     this.users
                    //         .querySelector(`[data-id='${dataId}']`)
                    //         .classList.remove("offline");
                }
            }
        }
    }
    validate() {
        this.sendButton.blur();
        this.input.disabled = true;
        this.send({
            id: this.active,
            message: this.input.value,
        });
    }
    writeEmail() {
        // show email recipients and subject fields 'a la spark'
        // show delay send with date picker
        // expand text area
        // activate editing tools absolute over text selection / sticky on top of text area 'a la spark'
        // join file button
    }
}
