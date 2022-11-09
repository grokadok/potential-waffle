class WSConnection {
    #authorized = 0;
    constructor(host) {
        this.ws = new WebSocket(host);
        this.ws.onopen = () => {
            this.ws.send(
                JSON.stringify({
                    active: localStorage.getItem("active_tab") ?? null,
                    email: login.email.input[0].value.trim(),
                    password: login.currentPassword.input[0].value,
                })
            );
        };
        this.ws.onmessage = (e) => {
            const data = JSON.parse(e.data);
            console.log(data);
            if (this.#authorized === 0) {
                if (data.name) {
                    this.#authorized = 1;
                    // login
                    login.head.classList.add("logged");
                    setTimeout(() => {
                        login.wrapper.className = "login hidden";
                        new ClassMain(data);
                    }, 300);

                    // const page_list = pageSelector(data.role),
                    //     chat_list = data.chats?.split(",") ?? [];
                    // login
                    //     .getElementsByClassName("login-header")[0]
                    //     .classList.add("logged");
                    // loadIn([main, navbar, topbar, mainChat]);
                    // setTimeout(function () {
                    //     login.className = "login hidden";
                    //     emptyEl(login);
                    //     mainView(data.name, page_list);
                    //     loadMain(chat_list);
                    //     loadOut([main, navbar, topbar, mainChat]);
                    //     if (parseInt(data.atempts) > 2)
                    //         msg.new({
                    //             content: `Bienvenue ${data["name"]} !
                    //             Pour information, ${data["atempts"]} tentatives de connexion
                    //             à votre compte ont échoué depuis la dernière connexion réussie.
                    //             Si ce nombre vous paraît anormal, vérifiez que votre compte soit bien sécurisé.`,
                    //         });
                    // }, 200);
                } else {
                    let style, left;
                    if (data > 0) {
                        if (data > 2 && data < 5) style = "warning";
                        else if (data > 4) style = "danger";
                        else style = null;
                        left = (7 - data).toString();
                        if (data < 7)
                            msg.new({
                                content: `Mot de passe erroné, essaie encore.
                                Nombre d'essais restants : ${left}`,
                                type: style,
                                btn0listener: () => {
                                    setTimeout(
                                        () =>
                                            login.currentPassword.input[0].focus(),
                                        10
                                    );
                                },
                            });
                        else if (data === 7)
                            msg.new({
                                content: `Trop d'essais consécutifs, merci de patienter quelques minutes avant de réessayer.`,
                            });
                    } else if (data === "-1") {
                        // impossible car adresse vérifiée en amont
                        msg.new({
                            content:
                                "Adresse mail invalide. Please try again, bitch.",
                            type: "warning",
                        });
                    } else if (data === "-2") {
                        msg.new({
                            content:
                                "Pensez à valider la création de votre compte par email et SMS.",
                            btn0listener: () => login.blankBox(),
                        });
                    } else if (data === "-3") {
                        msg.new({
                            content: `Veuillez patienter quelques minutes avant votre prochain essai,
                            ou réinitialisez votre mot de passe.`,
                            btn0listener: () => login.blankBox(),
                        });
                    } else if (data === "-4") {
                        msg.new({
                            content:
                                "Votre compte n'est qu'un vugaire compte client, vous n'avez rien à faire par ici.",
                            btn0listener: () => login.blankBox(),
                            type: "danger",
                        });
                    }
                }
            } else if (this.#authorized === 1) {
                if (data.response?.fail === "string") {
                    console.error(data.response.error);
                    msg.new({
                        content: data.response.fail,
                        type: "warning",
                    });
                } else if (data.f === 6) {
                    // replace with Tabulator.findTable(".tabulator[data-t='" + data.t + "']")

                    // find Field of type table and task data.t (when tabulator replaced by home made solution)
                    // let table = Field.find();
                    const divs = document.querySelectorAll(
                            `fieldset[data-f='6'][data-t='${data.t}'] > div`
                        ),
                        tableEvents = data.response.event ?? undefined,
                        tables = Tabulator.findTable(
                            `fieldset[data-f='6'][data-t='${data.t}'] > .tabulator`
                        );
                    delete data.response.event;
                    if (tables) {
                        for (let table of tables) {
                            table.replaceData(data.response.data);
                        }
                    }
                    for (let table of divs) {
                        if (!table.classList.contains("tabulator")) {
                            let tabu = new Tabulator(table, data.response);
                            if (tableEvents) {
                                for (const tableEvent of tableEvents) {
                                    tabu.on(
                                        tableEvent["listener"],
                                        eval(tableEvent["action"])
                                    );
                                }
                            }
                        }
                    }
                } else if ([7, 12].includes(data.f)) {
                    data.s === 8
                        ? BopChat.chats[0].parseData(data)
                        : Field.parseData(data);
                } else if ([8, 9, 10, 11].includes(data.f)) {
                    Modal.parseData(data);
                } else if (data.f === 13) {
                    // open ticket
                    if (data.i) {
                        const params = {
                            btn0listener: (e) => {
                                Modal.find(e.currentTarget).close();
                                socket.send({
                                    f: 19,
                                    i: data.response.chat.id,
                                });
                            },
                            btn0style: "theme",
                            btn0text: "fermer",
                            btn1text: "supprimer",
                            btn1style: "danger",
                            btn1listener: () => {
                                msg.new({
                                    content:
                                        "Voulez-vous supprimer ce ticket ?",
                                    btn1text: "supprimer",
                                    btn1type: "warning",
                                    btn1listener: () => {
                                        msg.new({
                                            content: "Under construction",
                                            type: "warning",
                                        });
                                        msg.close();
                                    },
                                });
                            },
                            btn2text: "appliquer",
                            btn2style: "success",
                            btn2listener: () => {
                                msg.new({
                                    content: "Under construction",
                                    type: "warning",
                                });
                            },
                            fields: [
                                {
                                    compact: true,
                                    grid: "2/1/3/5",
                                    name: "Sujet",
                                    placeholder: "Sujet",
                                    type: "input_string",
                                    value: data.response.ticket.subject,
                                },
                                {
                                    compact: true,
                                    grid: "2/5/3/6",
                                    name: "État",
                                    task: 7,
                                    type: "select",
                                    value: data.response.ticket.idstate,
                                },
                                {
                                    compact: true,
                                    grid: "2/6/3/7",
                                    name: "Priorité",
                                    task: 6,
                                    type: "select",
                                    value: data.response.ticket.idpriority,
                                },
                                {
                                    add: (el) => {
                                        loadNewContact({
                                            childOf: el.closest("modal"),
                                            name: el.value,
                                            parentId: Array.from(
                                                el.closest(".modal").children
                                            ).indexOf(el),
                                        });
                                    },
                                    compact: true,
                                    grid: "3/5/4/6",
                                    multi: false,
                                    name: "Client",
                                    placeholder: "Client",
                                    task: 3,
                                    type: "selectize",
                                    value: data.response.client ?? undefined,
                                },
                                {
                                    compact: true,
                                    grid: "3/6/4/7",
                                    multi: false,
                                    name: "Attribué à",
                                    placeholder: "Attribué à",
                                    task: 4,
                                    type: "selectize",
                                    value: data.response.assignee ?? undefined,
                                },
                                {
                                    compact: true,
                                    grid: "4/5/5/7",
                                    name: "Type",
                                    task: 5,
                                    type: "select",
                                    value: data.response.ticket.idtype[0]
                                        .idticket_type,
                                },
                                {
                                    add: (el) => modalAddTag(el),
                                    compact: true,
                                    grid: "5/5/6/7",
                                    label: "Tags",
                                    multi: true,
                                    name: "Tags",
                                    placeholder: "Etiquettes",
                                    task: 1,
                                    type: "selectize",
                                    value: data.response.ticket.tags,
                                },
                                {
                                    compact: true,
                                    grid: "3/1/7/5",
                                    name: "Description",
                                    placeholder: "Description",
                                    type: "quill",
                                    value: data.response.ticket.description,
                                },
                            ],
                            grid: 6,
                            title: "Ticket #" + data.i,
                        };
                        new Modal(params);
                        BopChat.chats[0].wrapper.classList.add("toggle");
                    }
                } else if (data.f === 14) {
                    BopChat.chats[0].addChat(data.chat);
                } else if (data.f === 15) {
                    // user typing in chat
                    console.log(
                        `user ${data.iduser} is typing in chat ${data.idchat}`
                    );
                    // if chat data.i displayed
                    // show user typing in chat messages
                    // else if chat tab displayed
                    // show typing icon in chat tab
                } else if (data.f === 16) {
                    // new message for chat data.i
                    const chat = BopChat.chats[0];
                    if (
                        data.response.last &&
                        !chat.content.querySelector(
                            `[data-i="${data.i}"] fieldset[data-t='${data.response.last.created}'][data-i='${data.response.last.iduser}']`
                        )
                    ) {
                        return socket.send({
                            f: 14,
                            i: data.i,
                        });
                    }
                    chat.addMessage(data.response.new);
                    if (
                        chat.content
                            .getElementsByTagName("datalist")[0]
                            .querySelector(
                                `[data-id='${data.response.new.iduser}']`
                            )
                            .getAttribute("data-position") === "user"
                    ) {
                        chat.input.value = "";
                        chat.input.disabled = false;
                    }
                    // receive new message and last message's server time
                    // if last message's server time corresponds

                    // if chat data.i displayed
                    // insert new message in chat
                    // if input field hasn't focus
                    // notification disabled on input field focus
                    // if chat opened
                    // else refresh whole chat
                } else if (data.f === 17) {
                    // Receive chat request from user
                    if (data.response.type === "request") {
                        // alert 'requete reçue', refuser, rejoindre, plus tard (timer 5min en loop, jusqu'à acceptation ou annulation)
                        msg.new({
                            type: "success",
                            content: data.response.success,
                            btn0text: "refuser",
                            btn0listener: () => {
                                socket.send({
                                    c: data.response.chat,
                                    f: 17,
                                    j: 0,
                                    u: data.response.sender,
                                });
                            },
                            btn1text: "accepter",
                            btn1listener: () => {
                                socket.send({
                                    c: data.response.chat,
                                    f: 17,
                                    j: 2,
                                    u: data.response.sender,
                                });
                                msg.close();
                            },
                            btn2text: "plus tard",
                            btn2listener: () => {
                                socket.send({
                                    c: data.response.chat,
                                    f: 17,
                                    j: 1,
                                    u: data.response.sender,
                                });
                            },
                        });
                    } else if (data.response.type === "response") {
                        // response from requested
                        msg.new({
                            content: "Requête acceptée !",
                            type: "success",
                        });
                        socket.send({
                            f: 14,
                            i: data.response.chat,
                        });
                    } else if (data.response.type === "success") {
                        msg.new({
                            content: data.response.success,
                            type: "success",
                        });
                    }
                } else if (data.f === 18) {
                    // refresh user list
                    BopChat.chats[0].updateUsers(data.chat);
                } else if (data.f === 19) {
                    data.chat.deserted
                        ? // if user that left, close chat
                          BopChat.chats[0].remove(data.chat.id)
                        : // else refresh user list
                          BopChat.chats[0].updateUsers(data.chat);
                } else if (data.f === 20) {
                    ClassMain.el.parseTab(data.i, data.response);
                } else if ([21, 22, 25, 27, 29, 32, 33, 34].includes(data.f)) {
                    // receive bopcal data
                    BopCal.parse(data);
                } else if (data.f === 2501) {
                    BopTable.parseData(data);
                }
            }
        };
        this.ws.onclose = (e) => {
            this.#authorized = 0;
            console.log(
                "Connection closed, code: " +
                    e.code +
                    " reason: " +
                    e.reason +
                    "."
            );
            // if (
            //     !document
            //         .getElementsByTagName("main")[0]
            //         .classList.contains("hidden")
            // ) {
            //     loadIn([main, navbar, topbar, login, mainChat]);
            //     setTimeout(function () {
            //         Modal.destroy();
            //         unblurElements([main, navbar, topbar]);
            //         tabuDestroy(main);
            //         BopChat.destroyAll();
            //         emptyEl([navbar, main, topbar, mainChat]);
            //         loginView();
            //         loadLogin();
            //         login.classList.remove("loading");
            //     }, 300);
            // }
            BopChat.destroyAll();
            BopTable.destroyAll();
            BopCal.destroyAll();
            Modal.destroy();
            if (ClassMain.el) {
                ClassMain.destroy();
                login.load();
            }
            if (e.code !== 1000) {
                msg.new({
                    content: "La connexion avec le serveur a été interrompue.",
                    type: "warning",
                });
            }
        };
    }
    status() {
        return this.#authorized;
    }
    readyState() {
        return this.ws.readyState;
    }
    close(message) {
        this.ws.close(1000, message);
    }
    // async onClose(e) {
    //     this.#authorized = 0;
    //     console.log(
    //         "Connection closed, code: " + e.code + " reason: " + e.reason + "."
    //     );
    //     if (!main.classList.contains("hidden")) {
    //         loadIn([main, navbar, topbar, login, mainChat]);
    //         setTimeout(function () {
    //             Modal.destroy();
    //             unblurElements([main, navbar, topbar]);
    //             tabuDestroy(main);
    //             chat.destroy();
    //             emptyEl([navbar, main, topbar, mainChat]);
    //             loginView();
    //             loadLogin();
    //             login.classList.remove("loading");
    //         }, 300);
    //     }
    //     if (e.code !== 1000) {
    //         msg.new({
    //             content: "La connexion avec le serveur a été interrompue.",
    //             type: "warning",
    //         });
    //     }
    // }
    /**
     * Sends websocket message, duh.
     * @param {JSON} message - Message content, duh.
     */
    send(message) {
        this.ws.send(JSON.stringify(message));
    }
    addEventListener(param) {
        this.ws.addEventListener(
            param.type,
            param.action,
            (param.options = {})
        );
    }
}
