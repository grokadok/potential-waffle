class Modal {
    static modals = [];
    static focus = [];
    static maxModals = 5;
    static maxChildren = 5;
    /**
     * Open a new modal.
     * @param {Object} options - Options to apply to the new modal.
     * @param {Object[]} [options.buttons] - Set buttons and their properties, if undefined, only cancel button.
     * @param {String} [options.buttons[].text] - Button text content.
     * @param {String} [options.buttons[].style] - Button style: info, success, warning, danger.
     * @param {Function} [options.buttons[].listener] - Button on click listener function.
     * @param {Boolean} [options.buttons.requireValid] - Button disabled if checkRequiredFields() returns false.
     * @param {Object} [options.childOf] - The parent modal if there's one.
     * @param {Object} options.fields - The fields of the modal.
     * @param {Function} [options.fields.add] - The function to add a new value to a selectize, whether to open a new modal or simply add the value.
     * @param {Array} [options.fields.attributes] - Attributes to apply to fieldElement.
     * @param {Boolean} [options.fields.collapsible] - NYI: check to make element collapsible.
     * @param {String} options.fields.name - Sets the name of the field.
     * @param {Boolean} [options.fields.required] - Whether the field is required or not to validate the modal.
     * @param {Function} [options.fields.rowDblClick] - Tabulator: function triggered by double click on row.
     * @param {Number} [options.fields.task] - The task to get data on input.
     * @param {String} options.fields.type - The type of the field. Accepted values: address, email, name, phone, select, selectize,text.
     * @param {Whatever} [options.fields.value] - The value to apply to the field on load.
     * @param {Object} options.grid - If set, overrides the default flex layout with a grid set as specified.
     * @param {Number} [options.parentId] - The index of the field that opened the modal, that may need data in return.
     * @param {Number} options.task - The task to trigger on modalOk().
     * @param {String} options.title - Sets the title of the modal, according to Captain Obvious.
     */
    constructor(options) {
        this.childOf = options.childOf ?? undefined;
        if (this.hasRoom()) {
            const modalContainer =
                document.getElementsByClassName("modal-container")[0];
            this.task = options.task;
            this.wrapper = document.createElement("div");
            this.header = document.createElement("div");
            this.content = document.createElement("div");
            this.footer = document.createElement("div");
            this.children = [];
            this.fields = [];
            this.title = document.createElement("h2");
            this.wrapper.className = "modal fadeout";
            if (options.grid) this.content.classList.add("g-6");
            this.title.textContent = options.title;

            // content elements
            this.content.addEventListener("pointerdown", (e) =>
                e.stopPropagation()
            );
            for (const field of options.fields) {
                const fieldObject = new Field(field);
                this.content.appendChild(fieldObject.wrapper);
                this.fields.push(fieldObject);
            }

            // first button cancel base
            this.buttons = [
                {
                    text: "annuler",
                    listener: () => {
                        msg.new({
                            content: `Êtes-vous sûr de vouloir annuler ? Toutes les informations rentrées seront perdues.`,
                            type: "warning",
                            btn1text: "continuer",
                            btn1style: "success",
                            btn1listener: () => {
                                this.close();
                                msg.close();
                            },
                        });
                    },
                    style: "danger",
                },
            ];
            // store buttons data into object
            if (options.buttons)
                for (const [key, value] of options.buttons.entries()) {
                    if (!this.buttons[key]) this.buttons[key] = {};
                    if (!objIsEmpty(value)) {
                        if (value.text) this.buttons[key].text = value.text;
                        if (value.listener)
                            this.buttons[key].listener = value.listener;
                        if (value.style) this.buttons[key].style = value.style;
                        if (value.requireValid)
                            this.buttons[key].requireValid = value.requireValid;
                    }
                }
            // for each stored button, generate element
            for (const [key, value] of this.buttons.entries()) {
                let button = document.createElement("button");
                button.textContent = value.text;
                button.addEventListener("click", () => {
                    value.listener ? value.listener() : this.modalOk();
                });
                button.classList.add(value.style ?? "success");
                this.buttons[key].node = button;
                this.footer.append(button);
            }
            this.header.appendChild(this.title);
            this.wrapper.append(this.header, this.content, this.footer);

            if (typeof this.childOf === "undefined") {
                modalContainer.appendChild(this.wrapper);
                setElementDraggable(this.wrapper, { constrain: true });
                Modal.modals.push(this);
                this.wrapper.addEventListener("pointerdown", (e) => {
                    Modal.find(e.currentTarget).setFocus();
                });
                this.setFocus();
            } else {
                this.parentId = options.parentId ?? undefined;
                this.childOf.wrapper.appendChild(this.wrapper);
                Modal.findParent(this.childOf).children.push(this);
            }

            this.checkRequiredFields();
            fadeIn(this.wrapper);
            this.wrapper.getElementsByTagName("input")[0]?.focus();
        } else
            return msg.new({
                content:
                    "Vous avez atteint le nombre maximal de fenêtres ouvertes.",
                type: "warning",
            });
    }
    /**
     * Checks validity of required element's fields.
     */
    checkRequiredFields() {
        for (const field of this.fields)
            if (field.required && !field.isValid) {
                disable(
                    this.buttons
                        .filter((x) => x.requireValid)
                        .map((x) => x.node)
                );
                return false;
            }
        enable(this.buttons.filter((x) => x.requireValid).map((x) => x.node));
        return true;
    }
    /**
     * Removes modal and its fields from any array and removes its element.
     */
    close() {
        if (typeof this.childOf !== "undefined") {
            Modal.findParent(this.childOf).children.splice(
                this.childOf.children.indexOf(this),
                1
            );
        } else Modal.modals.splice(Modal.modals.indexOf(this), 1);
        for (const field of this.fields) field.destroy();
        this.wrapper.remove();
    }
    /**
     * Kill all modals for logout.
     */
    static destroy() {
        for (let i = Modal.modals.length - 1; i >= 0; i--) {
            Modal.modals[i].close();
        }
    }
    /**
     * Returns the modal object from any element in it or the modal element itself.
     * @param {HTMLElement} el
     * @returns The modal object or false.
     */
    static find(el) {
        for (const modal of Modal.modals) {
            for (let i = modal.children.length - 1; i >= 0; i--) {
                const child = modal.children[i];
                if (child.wrapper === el || child.wrapper.contains(el)) {
                    return child;
                }
            }
            if (modal.wrapper === el || modal.wrapper.contains(el)) {
                return modal;
            }
        }
        return false;
    }
    /**
     * Returns the parent if there's one, else returns modal itself.
     * @param {Object} modal
     */
    static findParent(modal) {
        for (const parent of Modal.modals) {
            if (parent.wrapper.contains(modal.wrapper)) return parent;
        }
        return modal;
    }
    getData() {
        const parent = Modal.findParent(this);
        let message = {
                child: parent.children.indexOf(this),
                f: this.task,
                parent: Modal.modals.indexOf(parent),
            },
            content = {};
        // if (parent !== this) {
        //     message.child = parent.children.indexOf(this);
        //     message.field = this.parentId;
        // }
        for (const field of this.fields) {
            switch (field.type) {
                case "phone":
                    content[field.name] = field.phone ?? null;
                    break;
                case "quill":
                    // content[field.name] = field.quill.getContents();
                    content[field.name] = field.quill.getText().trim();
                    break;
                case "selectize":
                    content[field.name] =
                        field.selected.items.map((item) => item.id) ??
                        undefined;
                    break;
                case "select":
                    content[field.name] = field.selected;
                    break;
                default:
                    for (const input of field.input) {
                        if (input.value)
                            content[
                                input.getAttribute("aria-label") ??
                                    input.parentNode.getAttribute("aria-label")
                            ] = input.value;
                    }
                    break;
            }
        }
        message.content = content;
        return message;
    }
    /**
     * Checks there's room for a new modal according to maxChildren and maxModals.
     * @returns Duh.
     */
    hasRoom() {
        if (
            typeof this.childOf !== "undefined" &&
            Modal.findParent(this.childOf).children.length === Modal.maxChildren
        ) {
            return false;
        } else {
            if (Modal.modals.length === Modal.maxModals) {
                return false;
            }
        }
        return true;
    }
    /**
     *
     * @param {Object} [options]
     * @param {Boolean} options.confirm
     * @param {Object} options.data
     */
    modalOk(options) {
        let message;
        if (typeof options !== "undefined" && options.confirm) {
            message = options.data;
            message.confirm = options.confirm;
        } else message = this.getData();
        console.warn(message);
        socket.send(message);
    }
    static parseData(data) {
        const modal =
            data.child === -1
                ? Modal.modals[data.parent]
                : Modal.modals[data.parent].children[data.child];
        if (data.response.fail) {
            console.error(data.response.error);
            return msg.new({
                type: "danger",
                content: data.response.message ?? "Une erreur est survenue.",
            });
        }
        switch (data.f) {
            case 8: // send email
                msg.new({
                    content: data.response.success,
                    type: "success",
                    btn0listener: () => modal.close(),
                });
                break;
            case 9: // create ticket
                if (data.response.confirm) {
                    const alertMessage = data.response.confirm.content;
                    delete data.response;
                    delete data.user;
                    msg.new({
                        content: alertMessage,
                        btn1listener: () => {
                            modal.modalOk({
                                confirm: "new",
                                data: data,
                            });
                            msg.close();
                        },
                        btn1text: "créer",
                        btn2listener: () => {
                            msg.new({
                                content: "Under construction.",
                                type: "warning",
                            });
                            msg.close();
                        },
                        btn2text: "consulter",
                        type: "warning",
                    });
                } else {
                    refreshTabData(
                        document.querySelector(".tab:not(.fadeout)")
                    );
                    msg.new({
                        content: data.response.success,
                        type: "success",
                        btn0listener: () => modal.close(),
                    });
                }
                break;
            case 10: //create contact
            case 11: // create corp
                const parent = modal.childOf ?? null;
                // if modal!=parent
                // field.addSelectize(gnagnagna)
                parent?.fields[modal.parentId].addSelectize(
                    data.response.id,
                    data.response.name
                );
                // msg success etc...
                msg.new({
                    content: data.response.success,
                    type: "success",
                    btn0listener: () => modal.close(),
                });
                break;
            // default ?
        }
    }
    /**
     * Inserts this into Modal.modals if not included, then distribute focus in Modal.modals order.
     */
    setFocus() {
        // move this to the back of Modal.modals
        const mIndex = Modal.modals.indexOf(this);
        Modal.focus.includes(mIndex)
            ? Modal.focus.push(
                  Modal.focus.splice(Modal.focus.indexOf(mIndex), 1)[0]
              )
            : Modal.focus.push(mIndex);
        // redistribute z-index.
        let zIndex = 20;
        for (const int of Modal.focus)
            Modal.modals[int]
                ? (Modal.modals[int].wrapper.style["z-index"] = zIndex++)
                : Modal.focus.splice(Modal.focus.indexOf(int), 1);
    }
    /**
     * Sets the maximum number of children modal a modal can have.
     * @param {Number} int
     */
    static setMaxChildren(int) {
        Modal.maxChildren = int;
    }
    /**
     * Sets the maximum number of opened parent modal.
     * @param {Number} int
     */
    static setMaxModals(int) {
        Modal.maxModals = int;
    }
}

// Modals library
function loadNewCompany(options) {
    let email,
        childOf,
        parentId,
        name = "";
    if (options) {
        email = options.email ?? "";
        childOf = options.childOf ?? undefined;
        parentId = options.parentId ?? undefined;
        name = options.name ?? "";
    }
    const params = {
        // btn1text: "créer",
        // btn1style: "success",
        buttons: [{}, { text: "créer", requireValid: true }],
        fields: [
            {
                compact: true,
                name: "Nom",
                placeholder: "Evil Corp",
                required: true,
                type: "input_string",
                value: name,
            },
            {
                compact: true,
                name: "Adresse email",
                placeholder: "contact@evil.corp",
                required: true,
                type: "email",
                value: email,
            },
            {
                compact: true,
                label: "Téléphone",
                name: "Téléphone",
                placeholder: "0601234567",
                type: "phone",
            },
            {
                compact: true,
                name: "Adresse",
                type: "address",
            },
            {
                add: (el) => {
                    const modal = Modal.find(el),
                        field = modal.fields.indexOf(Field.find(el));
                    loadNewContact({
                        childOf: modal,
                        name: el.value,
                        parentId: field,
                    });
                },
                compact: true,
                multi: true,
                name: "Employés",
                placeholder: "Tannen Biff, McFly Marty...",
                task: 3,
                type: "selectize",
            },
            {
                add: (el) => modalAddTag(el),
                compact: true,
                multi: true,
                name: "Tags",
                placeholder: "Serpentard, moldu...",
                task: 1,
                type: "selectize",
            },
        ],

        childOf: childOf,
        parentId: parentId,
        task: 11,
        title: "Nouvelle entreprise",
    };
    // loadModal(params);
    new Modal(params);
}
function loadNewContact(options) {
    let email,
        childOf,
        parentId,
        firstName = "",
        lastName = "";
    if (options) {
        email = options.email ?? "";
        childOf = options.childOf ?? undefined;
        parentId = options.parentId ?? undefined;
        if (options.name !== undefined) {
            const names = options.name.split(" ");
            if (names.length > 1) {
                firstName = names.shift();
                for (const name of names) lastName = lastName + " " + name;
            } else firstName = options.name;
        }
    }
    const params = {
        buttons: [{}, { text: "créer", requireValid: true }],
        fields: [
            {
                compact: true,
                name: "Nom",
                placeholder: "Nom",
                required: true,
                type: "input_string",
                value: lastName.trim(),
            },
            {
                compact: true,
                name: "Prénom",
                placeholder: "Prénom",
                required: true,
                type: "input_string",
                value: firstName,
            },
            {
                add: (el) => {
                    const modal = Modal.find(el),
                        field = modal.fields.indexOf(Field.find(el));
                    loadNewCompany({
                        childOf: modal,
                        name: el.value,
                        parentId: field,
                    });
                },
                compact: true,
                multi: false,
                name: "Société",
                placeholder: "Société",
                task: 2,
                type: "selectize",
            },
            {
                compact: true,
                name: "Adresse email",
                placeholder: "Adresse email",
                required: true,
                type: "email",
                value: email,
            },
            {
                compact: true,
                name: "Téléphone portable",
                placeholder: "0601234567",
                type: "phone",
            },
            {
                compact: true,
                name: "Adresse",
                type: "address",
            },
            {
                add: (el) => {
                    msg.new({
                        content: `Souhaitez-vous créer le tag ${el.value} ?`,
                        btn1text: "Créer",
                        btn1style: "success",
                        btn1listener: async function () {
                            socket.send({
                                f: 12,
                                t: el.value,
                                x: Field.fields.indexOf(Field.find(el)),
                            });
                            el.value = "";
                            msg.close();
                            el.focus();
                        },
                    });
                },
                compact: true,
                multi: true,
                name: "Tags",
                placeholder: "Étiquettes",
                task: 1,
                type: "selectize",
            },
        ],
        // newmodal: newModalEl,
        childOf: childOf,
        parentId: parentId,
        task: 10,
        title: "Nouveau contact",
    };
    // loadModal(params);
    new Modal(params);
}
function loadNewEmail(contacts) {
    let dest = undefined;
    if (contacts) {
        dest = [];
        for (const contact of contacts) {
            dest.push(contact);
        }
    }
    const params = {
        buttons: [
            {},
            { text: "envoyer", requireValid: true },
            {
                text: "envoi décalé",
                style: "info",
                requireValid: true,
                listener: () => {
                    console.log(
                        "show date/hour picker and program mail sending"
                    );
                    msg.new({
                        content: "Under construction",
                        type: "warning",
                    });
                },
            },
        ],

        fields: [
            {
                add: (el) => {
                    const modal = Modal.find(el),
                        field = modal.fields.indexOf(Field.find(el));
                    loadNewContact({
                        childOf: modal,
                        name: el.value,
                        parentId: field,
                    });
                },
                compact: true,
                multi: true,
                name: "Destinataire",
                placeholder: "Destinataire",
                required: true,
                task: 0,
                type: "selectize",
                value: dest,
            },
            {
                add: (el) => {
                    const modal = Modal.find(el),
                        field = modal.fields.indexOf(Field.find(el));
                    loadNewContact({
                        childOf: modal,
                        name: el.value,
                        parentId: field,
                    });
                },
                compact: true,
                multi: true,
                name: "Cc",
                placeholder: "Copie carbone",
                task: 0,
                type: "selectize",
                collapsible: true,
            },
            {
                add: (el) => {
                    const modal = Modal.find(el),
                        field = modal.fields.indexOf(Field.find(el));
                    loadNewContact({
                        childOf: modal,
                        name: el.value,
                        parentId: field,
                    });
                },
                compact: true,
                multi: true,
                name: "Cci",
                placeholder: "Copie carbone invisible",
                task: 0,
                type: "selectize",
                collapsible: true,
            },
            {
                compact: true,
                name: "Sujet",
                placeholder: "Sujet de votre email",
                required: true,
                type: "input_string",
            },
            {
                compact: true,
                name: "Contenu",
                placeholder: "Contenu de votre email.",
                required: true,
                type: "quill",
            },
        ],
        task: 8,
        title: "Rédiger nouvel email",
    };
    // loadModal(params);
    new Modal(params);
}
function loadNewTicket(contact) {
    const client = contact ?? undefined,
        params = {
            buttons: [
                {},
                { text: "créer", requireValid: true },
                {
                    text: "créer & ouvrir",
                    style: "info",
                    requireValid: true,
                    listener: () => {
                        console.log(
                            "create ticket, then close modal and open ticket"
                        );
                        unblurElements([modalContainer, navbar, topbar, main]);
                        fadeOut(modalContainer);
                        resetModal(modal);
                        // open ticket
                    },
                },
            ],
            // btn1text: "créer",
            // btn1style: "success",
            // btn2modalOnly: true,
            // btn2text: "créer & ouvrir",
            // btn2style: "info",
            // btn2listener: function () {
            //     console.log("create ticket, then close modal and open ticket");
            //     unblurElements([modalContainer, navbar, topbar, main]);
            //     fadeOut(modalContainer);
            //     resetModal(modal);
            //     // open ticket
            // },
            fields: [
                {
                    add: (el) => {
                        const modal = Modal.find(el),
                            field = modal.fields.indexOf(Field.find(el));
                        loadNewContact({
                            childOf: modal,
                            name: el.value,
                            parentId: field,
                        });
                    },
                    compact: true,
                    multi: false,
                    name: "Client",
                    placeholder: "Hunter Rick",
                    required: true,
                    task: 3,
                    type: "selectize",
                    value: client,
                },
                {
                    compact: true,
                    name: "Sujet",
                    placeholder: "Sujet...",
                    required: true,
                    type: "input_string",
                },
                {
                    compact: true,
                    name: "Description",
                    placeholder: "Description...",
                    type: "quill",
                },
                {
                    compact: true,
                    name: "État",
                    task: 7,
                    type: "select",
                },
                {
                    compact: true,
                    name: "Type",
                    task: 5,
                    type: "select",
                },
                {
                    compact: true,
                    name: "Priorité",
                    placeholder: "Vital",
                    task: 6,
                    type: "select",
                },
                {
                    compact: true,
                    multi: false,
                    name: "Attribué à",
                    placeholder: "Attribué à...",
                    required: true,
                    task: 4,
                    type: "selectize",
                },
                {
                    add: (el) => modalAddTag(el),
                    compact: true,
                    multi: true,
                    name: "Tags",
                    placeholder: "Étiquettes",
                    task: 1,
                    type: "selectize",
                },
            ],
            task: 9,
            title: "Nouveau ticket",
        };
    // loadModal(params);
    new Modal(params);
}
/**
 * Opens the selected ticket in a modal.
 * @param {Number} id - Ticket id.
 */
function loadTicket(id) {
    if (id > 0) {
        socket.send({
            f: 13,
            i: id,
        });
    }
}
