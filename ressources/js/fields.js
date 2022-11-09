class Field {
    static fields = [];
    static timer;
    /**
     * @param {Object} params
     * @param {Function} [params.add] - The function to add a new value to a selectize, whether to open a new modal or simply add the value.
     * @param {Boolean} [params.collapsible=false] - Not yet implemented: check to make element collapsible.
     * @param {Boolean} [params.compact=false] - If compact, title hidden ?
     * @param {String} [params.label] - Value of the aria-label attribute, if not set then aria-label = name.
     * @param {Boolean} [params.modal=false] - Whether the field is part of a Modal object.
     * @param {Boolean} [params.multi=false] - Whether the select(ize) can get more than one selected value or not.
     * @param {String} params.name - Sets the name of the field.
     * @param {String} [params.placeholder] - Sets the placeholder value for the input field.
     * @param {Boolean} [params.required=false] - If field is required or not on form submission/modal validation.
     * @param {Number} [params.task] - The task to get data on input.
     * @param {String} params.type - The type of the field. Accepted values: address, email, name, phone, select, selectize,text.
     * @param {Whatever} [params.value] - The value to apply to the field on load.
     */
    constructor(params) {
        Field.fields.push(this);
        this.index = Field.fields.indexOf(this);
        this.wrapper = document.createElement("div");
        this.input = [];
        this.isValid = false;
        this.disabled = false;
        this.timer;
        this.add = params.add ?? undefined;
        // this.modal = params.modal ?? false; <- nope ?
        this.multi = params.multi;
        this.name = params.name;
        this.options = params.options ?? {};
        this.task = params.task ?? undefined;
        this.type = params.type;
        this.required = params.required ?? false;
        this.wrapper.style.gridArea = params.grid ?? null;
        if (typeof params.compact === "boolean" && params.compact)
            this.wrapper.classList.add("compact");
        this.wrapper.classList.add("field");
        this.wrapper.setAttribute("aria-label", this.name);
        const placeholder = params.placeholder ?? this.name,
            label = params.label ?? this.name,
            selectEvent = new Event("select");
        // const attributes = [
        //     ["placeholder", params.placeholder ?? ""],
        // ];
        switch (this.type) {
            case "address": {
                // this.wrapper;
                this.fields = [];
                let street = document.createElement("input"),
                    postcode = document.createElement("input"),
                    city = document.createElement("input"),
                    country = document.createElement("input");
                this.wrapper.setAttribute("aria-label", "Adresse");
                this.wrapper.classList.add("address");
                setElementAttributes(street, {
                    placeholder: "2 rue du Port",
                    name: "Numéro et voie",
                });
                setElementAttributes(postcode, {
                    placeholder: "87200",
                    name: "Code postal",
                });
                setElementAttributes(city, {
                    placeholder: "Sainte Bernadette",
                    name: "Ville",
                });
                setElementAttributes(country, {
                    placeholder: "France",
                    name: "Pays",
                });
                if (params.value) {
                    street.value = params.value.street;
                    postcode.value = params.value.postcode;
                    city.value = params.value.city;
                    country.value = params.value.country;
                }
                this.wrapper.append(street, postcode, city, country);
                this.input.push([street, postcode, city, country]);
                // later add address autocomplete/verification
                break;
            }
            case "calendar": {
                const index = this.index;
                let fieldElement = document.createElement("div");
                this.data = {};
                this.calendar = new FullCalendar.Calendar(fieldElement, {
                    events: function (info, successCallback, failureCallback) {
                        socket.send({
                            f: 21,
                            start: toCalDAVString(info.start.valueOf()),
                            end: toCalDAVString(info.end.valueOf()),
                            x: index,
                        });
                    },
                    headerToolbar: {
                        left: "prev,next today",
                        center: "title",
                        right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek",
                    },
                    height: "100%",
                    initialView: "dayGridMonth",
                    locale: "fr",
                    navLinks: "true",
                    nowIndicator: "true",
                    weekNumbers: "true",
                    weekNumberFormat: { week: "numeric" },
                });
                this.wrapper.style.zIndex = 0;
                this.wrapper.append(fieldElement);
                break;
            }
            case "checkbox": {
                let fieldElement = document.createElement("input");
                fieldElement.type = "checkbox";
                fieldElement.checked = this.value;
                this.input.push(fieldElement);
                this.wrapper.append(fieldElement);
                fieldElement.addEventListener("click", (e) => e.target.blur());
                break;
            }
            case "date": {
                let fieldElement = document.createElement("input");
                fieldElement.type = "date";
                fieldElement.required = true;
                fieldElement.value = toHTMLInputDateValue(
                    params.value ?? new Date()
                );
                this.input.push(fieldElement);
                this.wrapper.append(fieldElement);
                this.min = (date) =>
                    fieldElement.setAttribute(
                        "min",
                        toHTMLInputDateValue(date)
                    );
                this.max = (date) =>
                    fieldElement.setAttribute(
                        "max",
                        toHTMLInputDateValue(date)
                    );
                if (params.min) this.min(params.min);
                if (params.max) this.max(params.max);
                break;
            }
            case "datetime": {
                let fieldElement = document.createElement("input");
                fieldElement.type = "datetime-local";
                fieldElement.required = true;
                fieldElement.value = toHTMLInputDateTimeValue(
                    params.value ?? new Date()
                );
                this.input.push(fieldElement);
                this.wrapper.append(fieldElement);
                this.min = (date) =>
                    fieldElement.setAttribute(
                        "min",
                        toHTMLInputDateTimeValue(date)
                    );
                this.max = (date) =>
                    fieldElement.setAttribute(
                        "max",
                        toHTMLInputDateTimeValue(date)
                    );
                if (params.min) this.min(params.min);
                if (params.max) this.max(params.max);
                break;
            }
            case "email": {
                let fieldElement = document.createElement("input");
                setElementAttributes(fieldElement, {
                    type: "email",
                    pattern:
                        "^[\\w!#$%&’*+/=?`{|}~^-]+(?:\\.[\\w!#$%&’*+/=?`{|}~^-]+)*@(?:[a-zA-Z0-9-]+\\.)+[a-zA-Z]{2,}$",
                    // ["name", label],
                    placeholder: placeholder,
                });
                this.wrapper.append(fieldElement);
                fieldElement.value = params.value ?? "";
                if (this.required)
                    fieldElement.addEventListener("input", () => {
                        this.getValid();
                        if (Modal.find(fieldElement))
                            Modal.find(fieldElement).checkRequiredFields();
                    });
                this.input.push(fieldElement);
                break;
            }
            case "input_number": {
                let fieldElement = document.createElement("input");
                setElementAttributes(fieldElement, {
                    type: "number",
                    pattern: "\\d*",
                    min: params.min ?? "",
                    max: params.max ?? "",
                });
                this.wrapper.append(fieldElement);
                if (params.value) fieldElement.value = params.value;
                fieldElement.addEventListener("input", () => {
                    fieldElement.style.width = `${
                        fieldElement.value.length
                            ? fieldElement.value.length
                            : 1
                    }ch`;
                    if (this.required) {
                        fieldElement.addEventListener("input", () => {
                            this.getValid();
                            Modal.find(fieldElement)?.checkRequiredFields();
                        });
                    }
                });
                fieldElement.addEventListener("blur", () => {
                    if (!fieldElement.validity.valid)
                        fieldElement.value = params.min ?? 1;
                });
                this.input.push(fieldElement);
                break;
            }
            case "input_string": {
                let fieldElement = document.createElement("input");
                setElementAttributes(fieldElement, {
                    spellcheck: "false",
                    autocomplete: "off",
                    // ["name", label],
                    placeholder: placeholder,
                });
                this.wrapper.append(fieldElement);
                if (params.value) fieldElement.value = params.value;
                if (this.required) {
                    const field = this;
                    fieldElement.addEventListener("input", () => {
                        field.getValid();
                        Modal.find(fieldElement)?.checkRequiredFields();
                    });
                }
                this.input.push(fieldElement);
                break;
            }
            case "input_text": {
                let fieldElement = document.createElement("textarea");
                setElementAttributes(fieldElement, {
                    // ["name", label],
                    placeholder: placeholder,
                });
                this.wrapper.append(fieldElement);
                fieldElement.textContent = params.value ?? "";
                if (this.required) {
                    // const field = this;
                    fieldElement.addEventListener("input", () => {
                        this.getValid();
                        if (Modal.find(fieldElement))
                            Modal.find(fieldElement).checkRequiredFields();
                    });
                }
                this.input.push(fieldElement);
                break;
            }
            case "password":
            case "current-password":
            case "new-password": {
                let fieldElement = document.createElement("input");
                fieldElement.className = "password";
                setElementAttributes(fieldElement, {
                    type: "password",
                    pattern:
                        "^(?=.*[a-z])(?=.*[A-Z])(?=.*[\\d])(?=.*[!@#$%^&*]).{12,}$",
                    maxlength: "64",
                    // ["name", label],
                    placeholder: placeholder,
                    autocomplete: this.type !== "password" ? this.type : "",
                });
                this.wrapper.append(fieldElement);
                this.wrapper.insertAdjacentHTML(
                    "beforeend",
                    `<svg viewBox="0 0 24 23" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" tabindex="0">
                        <g>
                        <path d="M17.882,18.297C16.123,19.413 14.083,20.003 12,20C6.608,20 2.122,16.12 1.181,11C1.611,8.671 2.783,6.543 4.521,4.934L1.392,1.808L2.807,0.393L22.606,20.193L21.191,21.607L17.882,18.297ZM5.935,6.35C4.576,7.586 3.629,9.209 3.223,11C3.535,12.367 4.162,13.641 5.054,14.723C5.946,15.804 7.078,16.663 8.36,17.229C9.641,17.796 11.038,18.056 12.438,17.988C13.838,17.92 15.203,17.526 16.424,16.838L14.396,14.81C13.533,15.354 12.51,15.588 11.496,15.475C10.482,15.361 9.537,14.906 8.816,14.185C8.094,13.463 7.639,12.518 7.526,11.504C7.412,10.49 7.646,9.467 8.19,8.604L5.935,6.35ZM12.914,13.328L9.672,10.086C9.494,10.539 9.452,11.034 9.552,11.51C9.651,11.987 9.887,12.424 10.231,12.768C10.575,13.112 11.012,13.348 11.489,13.448C11.965,13.547 12.46,13.505 12.913,13.327L12.914,13.328ZM20.807,15.592L19.376,14.162C20.045,13.209 20.52,12.135 20.777,11C20.505,9.81 19.994,8.687 19.275,7.701C18.556,6.714 17.644,5.884 16.594,5.261C15.544,4.638 14.378,4.234 13.168,4.076C11.957,3.917 10.727,4.006 9.552,4.338L7.974,2.76C9.221,2.27 10.58,2 12,2C17.392,2 21.878,5.88 22.819,11C22.513,12.666 21.824,14.238 20.807,15.592ZM11.723,6.508C12.36,6.469 12.997,6.565 13.594,6.791C14.19,7.017 14.732,7.367 15.182,7.818C15.634,8.268 15.983,8.81 16.209,9.407C16.435,10.003 16.531,10.641 16.492,11.277L11.723,6.508Z"></path>
                        <g transform="matrix(1,0,0,1,12.008,10.9872)">
                            <g transform="matrix(1,0,0,1,-11,-9)">
                            <path class="hide" d="M11,0C16.392,0 20.878,3.88 21.819,9C20.879,14.12 16.392,18 11,18C5.608,18 1.122,14.12 0.181,9C1.121,3.88 5.608,0 11,0ZM11,16C13.04,16 15.018,15.307 16.613,14.035C18.207,12.764 19.323,10.988 19.777,9C19.321,7.013 18.205,5.24 16.611,3.97C15.016,2.7 13.038,2.009 11,2.009C8.962,2.009 6.984,2.7 5.389,3.97C3.795,5.24 2.679,7.013 2.223,9C2.677,10.988 3.793,12.764 5.387,14.035C6.982,15.307 8.961,16 11,16ZM11,13.5C9.807,13.5 8.662,13.026 7.818,12.182C6.974,11.338 6.5,10.194 6.5,9C6.5,7.807 6.974,6.662 7.818,5.818C8.662,4.974 9.807,4.5 11,4.5C12.194,4.5 13.338,4.974 14.182,5.818C15.026,6.662 15.5,7.807 15.5,9C15.5,10.194 15.026,11.338 14.182,12.182C13.338,13.026 12.194,13.5 11,13.5ZM11,11.5C11.663,11.5 12.299,11.237 12.768,10.768C13.237,10.299 13.5,9.663 13.5,9C13.5,8.337 13.237,7.701 12.768,7.232C12.299,6.763 11.663,6.5 11,6.5C10.337,6.5 9.701,6.763 9.232,7.232C8.763,7.701 8.5,8.337 8.5,9C8.5,9.663 8.763,10.299 9.232,10.768C9.701,11.237 10.337,11.5 11,11.5Z">
                            </path>
                            </g>
                        </g>
                        </g>
                    </svg>`
                );
                const svg = this.wrapper.getElementsByTagName("svg")[0];
                svg.addEventListener("click", (e) =>
                    togglePw(e.currentTarget.parentNode)
                );
                svg.addEventListener("keydown", (e) => {
                    if (e.code === "Space" || e.code === "Enter")
                        togglePw(e.currentTarget.parentNode);
                });
                if (this.type === "new-password")
                    fieldElement.addEventListener("input", function () {
                        const field = Field.find(fieldElement);
                        field.getValid();
                        if (field.modal)
                            Modal.find(fieldElement)?.checkRequiredFields();
                    });
                this.input.push(fieldElement);
                break;
            }
            case "phone": {
                let fieldElement = document.createElement("input");
                setElementAttributes(fieldElement, {
                    name: label,
                    placeholder: placeholder,
                });
                this.wrapper.append(fieldElement);
                this.intlTel = intlTelInput(fieldElement, {
                    utilsScript: "./assets/intlTelInput/js/utils.js",
                    initialCountry: "fr",
                    preferredCountries: ["fr"],
                    onlyCountries: ["be", "ch", "de", "es", "fr", "gb", "it"],
                    localizedCountries: {
                        de: "Deutschland",
                        ch: "Schweiz",
                        it: "Italia",
                        es: "España",
                    },
                });
                fieldElement.addEventListener("input", function () {
                    fieldElement.removeAttribute("data-phone");
                    // const field = Field.find(fieldElement);
                    if (this.required) {
                        this.getValid();
                        if (Modal.find(fieldElement))
                            Modal.find(fieldElement).checkRequiredFields();
                    }
                });
                this.input.push(fieldElement);
                break;
            }
            case "picker": {
                this.toggleOption = (value) => {
                    // get state of option
                    const element = this.wrapper.querySelector(
                        `[data-value="${value}"]`
                    );
                    if (!element)
                        return console.warn(
                            `No option with value = ${value} found.`
                        );
                    const state = element.classList.contains("selected");
                    // if !this.multi, remove all selected
                    if (!this.multi)
                        this.wrapper
                            .querySelectorAll(".selected")
                            .forEach((el) => el.classList.remove("selected"));
                    // apply toggle to option
                    state
                        ? element.classList.remove("selected")
                        : element.classList.add("selected");
                    this.wrapper.dispatchEvent(selectEvent);
                };
                this.setOptions = (options) => {
                    // empty picker
                    this.wrapper.innerHTML = "";
                    // for each option, create box
                    options.forEach((option) => {
                        let div = document.createElement("div");
                        div.textContent = option[1];
                        div.setAttribute("data-value", option[0]);
                        div.tabIndex = 0;
                        div.addEventListener("keyup", (e) => {
                            if (e.code === "Enter" || e.code === "Space")
                                this.toggleOption(
                                    e.target.getAttribute("data-value")
                                );
                        });
                        this.wrapper.append(div);
                    });
                };
                this.disable = () => {
                    if (!this.disabled) {
                        this.wrapper.classList.add("disabled");
                        Array.from(
                            this.wrapper.getElementsByTagName("div")
                        ).forEach((option) => (option.tabIndex = -1));
                        this.disabled = true;
                    }
                };
                this.enable = () => {
                    if (this.disabled) {
                        this.wrapper.classList.remove("disabled");
                        Array.from(
                            this.wrapper.getElementsByTagName("div")
                        ).forEach((option) => (option.tabIndex = 0));
                        this.disabled = false;
                    }
                };
                this.clear = () => {
                    Array.from(
                        this.wrapper.querySelectorAll(".selected")
                    ).forEach((option) => option.classList.remove("selected"));
                };
                if (params.options) this.setOptions(params.options);
                this.wrapper.addEventListener("click", (e) => {
                    if (e.target.getAttribute("data-value"))
                        this.toggleOption(e.target.getAttribute("data-value"));
                });
                this.wrapper.classList.add("picker");
                break;
            }
            case "quill": {
                let fieldElement = document.createElement("div");
                const toolbarOptions = [
                        ["bold", "italic", "underline", "strike"], // toggled buttons
                        ["blockquote", "code-block"],

                        [{ header: 1 }, { header: 2 }], // custom button values
                        [{ list: "ordered" }, { list: "bullet" }],
                        [{ script: "sub" }, { script: "super" }], // superscript/subscript
                        [{ indent: "-1" }, { indent: "+1" }], // outdent/indent
                        [{ direction: "rtl" }], // text direction

                        [{ size: ["small", false, "large", "huge"] }], // custom dropdown
                        [{ header: [1, 2, 3, 4, 5, 6, false] }],

                        [{ color: [] }, { background: [] }], // dropdown with defaults from theme
                        [{ font: [] }],
                        [{ align: [] }],

                        ["clean"],
                    ],
                    options = {
                        debug: "warn",
                        modules: {
                            toolbar: toolbarOptions,
                        },
                        placeholder: placeholder
                            ? placeholder
                            : "Votre texte...",
                        theme: "bubble",
                    };
                this.wrapper.append(fieldElement);
                this.quill = new Quill(fieldElement, options);
                if (this.required) {
                    this.quill.on("text-change", () => {
                        this.getValid();
                        if (Modal.find(fieldElement))
                            Modal.find(fieldElement).checkRequiredFields();
                    });
                }
                break;
            }
            case "select": {
                let container = document.createElement("div"),
                    fieldElement = document.createElement("div");
                this.ul = document.createElement("ul");
                this.ul.className = "fadeout";
                this.set = (id) => {
                    this.input[0].classList.remove("empty");
                    this.input[0].textContent = this.options[id].content;
                    this.input[0].setAttribute("data-value", id);
                    if (this.selected)
                        this.options[this.selected].li.classList.remove(
                            "selected"
                        );
                    this.selected = id;
                    this.options[this.selected].li.classList.add("selected");
                    this.input[0].dispatchEvent(selectEvent);
                    fadeOut(this.ul);
                };
                // this.ul.className = "fadeout";
                this.ul.setAttribute("role", "listbox");
                setElementAttributes(fieldElement, {
                    "data-value": params.value,
                    tabindex: "0",
                });
                fieldElement.textContent = placeholder;
                fieldElement.classList.add("empty");
                this.wrapper.classList.add("select");
                container.className = "container";
                container.append(fieldElement, this.ul);
                this.wrapper.append(container);
                const ulToggle = () => {
                    if (this.ul.classList.contains("fadeout")) {
                        this.ul.classList.remove("fadeout");
                        hideOnClickOutside(this.ul, container);
                    } else this.ul.classList.add("fadeout");
                };
                fieldElement.addEventListener("click", () => ulToggle());
                fieldElement.addEventListener("keydown", (e) => {
                    switch (e.code) {
                        case "ArrowDown":
                            if (this.ul.children.length > 0) {
                                this.ul.classList.remove("fadeout");
                                this.ul.children[0].focus();
                            }
                            break;
                        case "Escape":
                        case "Tab":
                            this.ul.classList.add("fadeout");
                            break;
                        // case escape: hide ul
                        // case tab: hide ul
                        // case shift tab: hide ul

                        // same with li: if up while first li, hide ul, focus fieldElement
                    }
                });
                this.input.push(fieldElement);
                if (this.task) {
                    socket.send({
                        f: 7,
                        s: this.task,
                    });
                } else {
                    // populate from this.options{number id, string content}
                    for (const [id, content] of Object.entries(this.options)) {
                        let li = document.createElement("li"),
                            span = document.createElement("span");
                        setElementAttributes(li, {
                            "data-select": id,
                            tabindex: "0",
                        });
                        span.textContent = content;
                        li.append(span);
                        li.addEventListener("click", () => {
                            this.set(id);
                        });
                        li.addEventListener("keydown", (e) => {
                            switch (e.code) {
                                case "Enter":
                                case "Space":
                                    this.set(id);
                                    break;
                                case "ArrowUp":
                                    e.preventDefault();
                                    if (li !== ul.firstChild)
                                        li.previousElementSibling.focus();
                                    else this.input[0].focus();
                                    break;
                                case "ArrowDown":
                                    e.preventDefault();
                                    if (li !== ul.lastChild)
                                        li.nextElementSibling.focus();
                                    break;
                                case "Tab":
                                    fadeOut(ul);
                            }
                        });
                        this.ul.append(li);
                        this.options[id] = { content: content, li: li };
                    }
                    console.info(params.value);
                    if (typeof params.value !== "undefined")
                        this.set(params.value);
                }
                this.disable = () => {
                    if (!this.disabled) {
                        this.wrapper.classList.add("disabled");
                        fieldElement.tabIndex = -1;
                        Array.from(this.ul.children).forEach(
                            (li) => (li.tabIndex = -1)
                        );
                        this.disabled = true;
                    }
                };
                this.enable = () => {
                    if (this.disabled) {
                        this.wrapper.classList.remove("disabled");
                        fieldElement.tabIndex = 0;
                        Array.from(this.ul.children).forEach(
                            (li) => (li.tabIndex = 0)
                        );
                        this.disabled = false;
                    }
                };
                break;
            }
            case "selectize": {
                const field = this;
                let container = document.createElement("div"),
                    add = document.createElement("button"),
                    fieldElement = document.createElement("input");
                this.selected = {
                    wrapper: document.createElement("div"),
                    items: [],
                };
                this.ul = document.createElement("ul");
                // this.ul.className = "fadeout";
                this.ul.setAttribute("role", "listbox");
                setElementAttributes(fieldElement, {
                    spellcheck: "false",
                    autocomplete: "off",
                    placeholder: placeholder,
                });
                this.wrapper.classList.add("selectize");
                container.className = "container";
                container.append(fieldElement, this.ul);
                this.selected.wrapper.className = "selected";
                if (params.add) {
                    // remove the + when modal children at max capacity
                    add.textContent = "+";
                    container.append(add);
                    add.addEventListener("click", function (e) {
                        let names = [];
                        for (const child of field.selected.wrapper.children) {
                            names.push(child.textContent);
                        }
                        if (!names.includes(fieldElement.value)) {
                            // fadeOut(field.ul, { hide: true });
                            params.add(fieldElement);
                        }
                    });
                }
                if (params.value) {
                    for (const [index, item] of params.value.entries()) {
                        let span = document.createElement("span");
                        if (item.content) {
                            span.textContent = item.group
                                ? `${item.content} (${item.group})`
                                : item.content;
                        } else {
                            span.textContent = item.group;
                        }
                        span.setAttribute("tabindex", "0");
                        span.addEventListener("click", () => {
                            this.selected.items.splice(
                                this.selected.items[index],
                                1
                            );
                            span.remove();
                        });
                        this.selected.items.push({
                            id: item["data-select"],
                            content: item.content,
                            element: span,
                            email: item.email ?? undefined,
                            group: item.group ?? undefined,
                        });
                        this.selected.wrapper.append(span);
                    }
                }
                this.wrapper.append(container, this.selected.wrapper);
                fieldElement.addEventListener("input", () => {
                    field.fetchDataTimer();
                });
                fieldElement.addEventListener("keydown", (e) => {
                    // remplacer par navigation globale dans l'app
                    // const field = Field.find(fieldElement);
                    switch (e.code) {
                        case "ArrowDown":
                            e.preventDefault();
                            if (field.ul.getElementsByTagName("li").length > 0)
                                field.ul.getElementsByTagName("li")[0].focus();
                            else if (
                                field.wrapper.nextElementSibling?.getElementsByTagName(
                                    "input"
                                )[0]
                            )
                                field.wrapper.nextElementSibling
                                    .getElementsByTagName("input")[0]
                                    .focus();
                            else if (
                                field.wrapper.nextElementSibling?.getElementsByTagName(
                                    "textarea"
                                )[0]
                            )
                                field.wrapper.nextElementSibling
                                    .getElementsByTagName("textarea")[0]
                                    .focus();
                            break;
                        case "ArrowUp":
                            e.preventDefault();
                            if (
                                field.wrapper.previousElementSibling?.getElementsByTagName(
                                    "input"
                                )[0]
                            )
                                field.wrapper.previousElementSibling
                                    .getElementsByTagName("input")[0]
                                    .focus();
                            else if (
                                field.wrapper.previousElementSibling?.getElementsByTagName(
                                    "textarea"
                                )[0]
                            )
                                field.wrapper.previousElementSibling
                                    .getElementsByTagName("textarea")[0]
                                    .focus();
                            break;
                        case "Escape":
                            fieldElement.blur();
                            break;
                        case "Enter":
                            // const modal = Modal.find(field.wrapper);
                            if (
                                params.add &&
                                Modal.find(field.wrapper)?.hasRoom()
                            ) {
                                let names = [];
                                for (const child of field.selected.wrapper
                                    .children)
                                    names.push(child.textContent);
                                if (!names.includes(fieldElement.value))
                                    params.add(fieldElement);
                            }
                            break;
                    }
                });
                fieldElement.addEventListener("focus", () => {
                    if (fieldElement.value.split(" ").length < 5) {
                        const field = Field.find(fieldElement);
                        field.fetchDataTimer();
                    }
                });
                this.input.push(fieldElement);
                break;
            }
            case "boptable": {
                let fieldElement = document.createElement("div");
                this.wrapper.append(fieldElement);
                params.options.caption = this.name;
                params.options.wrapper = fieldElement;
                new BopTable(params.options);
                break;
            }
        }
        if (this.required) this.getValid();
    }
    /**
     * Adds/replaces item to selectize's selected.
     * @param {Number} id
     * @param {String} name
     */
    async addSelectize(id, name) {
        const field = this,
            modal = Modal.find(this.wrapper);
        let span = document.createElement("span"),
            selected = {
                id: id,
                element: span,
            };
        span.textContent = name;
        span.setAttribute("tabindex", "0");
        if (!this.multi) {
            removeChildren(this.selected.wrapper, true);
            this.selected.items = [];
        }
        this.selected.items.push(selected);
        this.selected.wrapper.append(span);
        span.addEventListener("click", (e) => {
            if (e.target.closest(".bopcal")) e.stopPropagation();
            field.selected.items.splice(
                field.selected.items.indexOf(selected),
                1
            );
            span.remove();
            field.isValid = field.selected.items.length > 0 ? true : false;
            if (modal) modal.checkRequiredFields();
        });
        span.addEventListener("keydown", (e) => {
            if (e.code === "Space" || e.code === "Enter") {
                field.selected.items.splice(
                    field.selected.items.indexOf(selected),
                    1
                );
                span.remove();
                field.isValid = field.selected.items.length > 0 ? true : false;
                if (modal) modal.checkRequiredFields();
            }
        });
        this.input[0].value = "";
        // fadeOut(this.ul);
        removeChildren(this.ul, true);
        setTimeout(() => {
            field.input[0].focus();
        }, 50);
        this.isValid = true;
        if (modal) modal.checkRequiredFields();
    }
    /**
     * Removes the field from Field.fields and DOM.
     */
    destroy() {
        Field.fields.splice(this.index, 1);
        if (this.type === "table") tabuDestroy(this.wrapper);
        this.wrapper.remove();
    }
    /**
     * Requests field data to server.
     */
    fetchData() {
        socket.send({
            f: 7,
            s: this.task,
            i: this.input[0].value,
            x: this.index,
        });
    }
    /**
     * Delays data server fetch on input in field.
     * @param {HTMLElement} el - Input field.
     * @param {Number} task - Server function selector for data fetching.
     */
    fetchDataTimer() {
        clearTimeout(this.timer);
        if (typeof this.task !== "undefined") {
            if (this.input[0].value) {
                this.timer = setTimeout(() => {
                    this.fetchData();
                }, 50);
            } else {
                clearTimeout(this.timer);
                // fadeOut(this.ul);
                removeChildren(this.ul, true);
            }
        }
    }
    /**
     * Find Field object through attributes or HTML element.
     * @param {Object[]|HTMLElement} params
     * @returns {Object[]|Object}
     */
    static find(params) {
        if (typeof params.parentElement === "undefined") {
            let fields = [];
            for (const field of Field.fields) {
                let a;
                for (const [key, value] of Object.entries(params)) {
                    a = field[key] === value ? true : false;
                }
                if (a) fields.push(field);
            }
            return fields;
        } else {
            for (const field of Field.fields) {
                if (
                    field.wrapper === params ||
                    field.wrapper.contains(params) ||
                    field.input.includes(params)
                ) {
                    return field;
                }
            }
        }
    }
    getValid() {
        const el = this.input[0];
        // el = document.contains(this.wrapper) ? this.wrapper : this.input[0];
        switch (this.type) {
            case "email":
            case "password":
            case "current-password":
            case "new-password":
                if (this.input[0].value) {
                    if (this.input[0].validity.valid) {
                        this.isValid = true;
                        el.classList.add("valid");
                        el.classList.remove("invalid");
                        this.wrapper.setAttribute("aria-label", this.name);
                    } else {
                        this.isValid = false;
                        el.classList.add("invalid");
                        el.classList.remove("valid");
                        this.wrapper.setAttribute(
                            "aria-label",
                            this.name + " (invalide)"
                        );
                    }
                } else {
                    this.isValid = false;
                    el.classList.remove("invalid", "valid");
                    this.wrapper.setAttribute("aria-label", this.name);
                }
                break;
            case "input_string":
            case "input_text":
                this.isValid = this.input[0].value ? true : false;
                break;
            case "phone":
                if (this.input[0].value) {
                    if (this.intlTel.isValidNumber()) {
                        this.isValid = true;
                        this.phone = this.intlTel.getNumber();
                        el.classList.add("valid");
                        el.classList.remove("invalid");
                        this.wrapper.setAttribute("aria-label", this.name);
                    } else {
                        this.isValid = false;
                        this.phone = undefined;
                        el.classList.add("invalid");
                        el.classList.remove("valid");
                        this.wrapper.setAttribute(
                            "aria-label",
                            this.name + " (invalide)"
                        );
                    }
                } else {
                    this.isValid = false;
                    this.phone = undefined;
                    el.classList.remove("invalid", "valid");
                    this.wrapper.setAttribute("aria-label", this.name);
                }
                break;
            case "quill":
                this.isValid = this.quill.getText().trim().length
                    ? true
                    : false;
                break;
            case "selectize":
                this.isValid = this.selected.length > 0 ? true : false;
                break;
        }
    }
    static parseData(data) {
        switch (data.f) {
            case 7:
                if (data.i) {
                    const field = Field.fields[data.x],
                        input = field.input[0],
                        ul = field.ul;
                    if (data.response?.fail) {
                        msg.new({
                            content: data.response.fail,
                            btn0listener: () => input.focus(),
                        });
                        input.value = input.value.trim();
                    } else if (data.response.content.length) {
                        let ulList = [];
                        for (const item of field.selected.items) {
                            ulList.push(item.id);
                        }

                        removeChildren(ul, true);
                        for (const obj of data.response.content) {
                            if (
                                obj.id !== null &&
                                !ulList.includes(`${obj.id}`)
                            ) {
                                let li = document.createElement("li"),
                                    span = document.createElement("span");
                                li.setAttribute("data-select", obj.id);
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
                                        let btn =
                                            document.createElement("button");
                                        btn.textContent = role;
                                        btn.disabled = true;
                                        li.append(btn);
                                    }
                                }
                                if (obj.email) {
                                    li.setAttribute("data-email", obj.email);
                                    let email = document.createElement("span");
                                    email.textContent = `(${obj.email})`;
                                    span.insertAdjacentElement(
                                        "afterend",
                                        email
                                    );
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
                            input.value.split(" ")
                        );
                        for (const child of ul.children) {
                            child.addEventListener("keydown", (e) =>
                                field.selectizeKeysNav(e)
                            );
                            child.addEventListener("click", (e) => {
                                if (e.target.closest(".bopcal"))
                                    e.stopPropagation();
                                let arr = [];
                                Array.from(
                                    e.currentTarget.getElementsByTagName("span")
                                ).map((e) => arr.push(e.textContent));
                                field.addSelectize(
                                    e.currentTarget.getAttribute("data-select"),
                                    arr.join(" ")
                                );
                            });
                        }
                        // ul.children.length > 0
                        //     ? fadeIn(ul, {
                        //           dropdown: ul.closest(".field"),
                        //       })
                        //     : fadeOut(ul);
                    } else removeChildren(ul, true);
                    // else fadeOut(ul);

                    // else fadeOut(ul);
                } else if (data.response?.content) {
                    // for selects
                    let fields = Field.find({
                        type: "select",
                        task: data.s,
                    });
                    for (let field of fields) {
                        const loadValue =
                                field.input[0].getAttribute("data-value") ?? "",
                            ul = field.ul;
                        removeAttributes(field.input[0], [
                            "data-value",
                            "data-f",
                            "data-s",
                        ]);
                        for (const obj of data.response.content) {
                            let li = document.createElement("li"),
                                span = document.createElement("span");
                            setElementAttributes(li, {
                                "data-select": obj.id,
                                tabindex: "0",
                            });
                            span.textContent = obj.content;
                            if (loadValue && loadValue === obj.id) {
                                field.input[0].textContent = obj.content;
                                field.input[0].classList.remove("empty");
                                li.className = "selected";
                            }
                            li.append(span);
                            li.addEventListener("click", () => {
                                field.set(obj.id);
                            });
                            li.addEventListener("keydown", (e) => {
                                switch (e.code) {
                                    case "Enter":
                                    case "Space":
                                        field.set(obj.id);
                                        break;
                                    case "ArrowUp":
                                        e.preventDefault();
                                        if (li !== ul.firstChild)
                                            li.previousElementSibling.focus();
                                        else field.input[0].focus();
                                        break;
                                    case "ArrowDown":
                                        e.preventDefault();
                                        if (li !== ul.lastChild)
                                            li.nextElementSibling.focus();
                                        break;
                                    case "Tab":
                                        fadeOut(ul);
                                }
                            });
                            ul.append(li);
                            field.options[obj.id] = {
                                content: obj.content,
                                li: li,
                            };
                        }
                        if (!field.input[0].value) {
                            const first = ul.getElementsByTagName("li")[0];
                            first.className = "selected";
                            field.selected = first.getAttribute("data-select");
                            field.input[0].value =
                                first.getElementsByTagName(
                                    "span"
                                )[0].textContent;
                        }
                        // field.input[0].addEventListener("focus", () => {
                        //     fadeIn(ul, {
                        //         dropdown: ul.closest(".field"),
                        //     });
                        //     ul.children[0].focus();
                        // });
                        // field.input[0].addEventListener("click", () => {
                        //     fadeIn(ul, {
                        //         dropdown: ul.closest(".field"),
                        //     });
                        //     ul.children[0].focus();
                        // });
                        // ul.addEventListener("keydown", (e) => {
                        //     switch (e.code) {
                        //         case "Escape":
                        //             fadeOut(ul);
                        //     }
                        // });
                    }
                }
                break;
            case 12:
                if (data.response.id)
                    Field.fields[data.x].addSelectize(data.response.id, data.t);
                break;
            // case 21:
            //     // populate calendar
            //     const field = Field.fields[data.x];
            //     for (const calendar of data.response) {
            //         if (!field.data[calendar.name])
            //             field.data[calendar.name] = {
            //                 role: calendar.role,
            //                 events: {},
            //             };
            //         field.calendar.batchRendering(() => {
            //             for (const event of calendar.events) {
            //                 // console.log(icalToObject(event.data));
            //                 const eventObject = icalToObject(event.data);
            //                 field.data[calendar.name].events[eventObject.uid] =
            //                     {
            //                         etag: event.etag,
            //                         href: event.href,
            //                         data: eventObject,
            //                     };
            //                 delete field.data[calendar.name].events[
            //                     eventObject.uid
            //                 ].data.uid;
            //                 field.calendar.addEvent({
            //                     id: eventObject.uid,
            //                     title: eventObject.VCALENDAR.VEVENT.TITLE,
            //                     // allDay: create condition to check wether the event is allday or not
            //                     // start:,
            //                     // end:,
            //                 });
            //             }
            //         });
            //     }
            //     break;
        }

        // if (data.f === 7) {
        //     if (data.i) {
        //         const field = Field.fields[data.x],
        //             input = field.input[0],
        //             ul = field.ul;
        //         if (data.response[0]?.fail) {
        //             msg.new({
        //                 content: data.response[0].fail,
        //                 btn0listener: () => input.focus(),
        //             });
        //             input.value = input.value.trim();
        //         } else if (data.response[0]?.content) {
        //             let ulList = [];
        //             for (const item of field.selected.items) {
        //                 ulList.push(item.id);
        //             }

        //             removeChildren(ul, true);
        //             for (const obj of data.response) {
        //                 if (obj.id !== null && !ulList.includes(`${obj.id}`)) {
        //                     let li = document.createElement("li"),
        //                         span = document.createElement("span");
        //                     li.setAttribute("data-select", obj.id);
        //                     li.setAttribute("tabindex", "0");
        //                     span.textContent = obj.content;
        //                     li.append(span);
        //                     if (obj.secondary) {
        //                         let span = document.createElement("span");
        //                         span.textContent = `(${obj.secondary})`;
        //                         li.append(span);
        //                     }
        //                     if (obj.role) {
        //                         const roles = obj.role.split(",");
        //                         for (const role of roles) {
        //                             let btn = document.createElement("button");
        //                             btn.textContent = role;
        //                             btn.disabled = true;
        //                             li.append(btn);
        //                         }
        //                     }
        //                     if (obj.email) {
        //                         li.setAttribute("data-email", obj.email);
        //                         let email = document.createElement("span");
        //                         email.textContent = `(${obj.email})`;
        //                         span.insertAdjacentElement("afterend", email);
        //                     }
        //                     if (
        //                         (obj.status && obj.status === 1) ||
        //                         (obj.inchat && obj.inchat === 1)
        //                     ) {
        //                         li.classList.add("offline");
        //                     }
        //                     ul.append(li);
        //                 }
        //             }
        //             highlightSearch(
        //                 Array.from(ul.getElementsByTagName("span")),
        //                 input.value.split(" ")
        //             );
        //             for (const child of ul.children) {
        //                 child.addEventListener("keydown", (e) =>
        //                     field.selectizeKeysNav(e)
        //                 );
        //                 child.addEventListener("click", (e) => {
        //                     let arr = [];
        //                     Array.from(
        //                         e.currentTarget.getElementsByTagName("span")
        //                     ).map((e) => arr.push(e.textContent));
        //                     field.addSelectize(
        //                         e.currentTarget.getAttribute("data-select"),
        //                         arr.join(" ")
        //                     );
        //                 });
        //             }
        //             // ul.children.length > 0
        //             //     ? fadeIn(ul, {
        //             //           dropdown: ul.closest(".field"),
        //             //       })
        //             //     : fadeOut(ul);
        //         } else removeChildren(ul, true);
        //         // else fadeOut(ul);

        //         // else fadeOut(ul);
        //     } else if (data.response[0]?.content) {
        //         // for selects
        //         let fields = Field.find({
        //             type: "select",
        //             task: data.s,
        //         });
        //         for (let field of fields) {
        //             const loadValue =
        //                     field.input[0].getAttribute("data-value") ?? "",
        //                 ul = field.ul;
        //             removeAttributes(field.input[0], [
        //                 "data-value",
        //                 "data-f",
        //                 "data-s",
        //             ]);
        //             function switchSelect(el) {
        //                 field.input[0].textContent =
        //                     el.getElementsByTagName("span")[0].textContent;
        //                 field.input[0].classList.remove("empty");
        //                 for (let child of el.parentNode.children) {
        //                     child === el
        //                         ? (child.className = "selected")
        //                         : (child.className = "");
        //                 }
        //                 field.selected = el.getAttribute("data-select");
        //                 fadeOut(ul);
        //             }
        //             for (const obj of data.response) {
        //                 let li = document.createElement("li"),
        //                     span = document.createElement("span");
        //                 setElementAttributes(li, [
        //                     ["data-select", obj.id],
        //                     ["tabindex", "0"],
        //                 ]);
        //                 span.textContent = obj["content"];
        //                 if (loadValue && loadValue === obj.id) {
        //                     field.input[0].textContent = obj["content"];
        //                     field.input[0].classList.remove("empty");
        //                     li.className = "selected";
        //                 }
        //                 li.append(span);
        //                 li.addEventListener("click", function () {
        //                     switchSelect(li);
        //                 });
        //                 li.addEventListener("keydown", (e) => {
        //                     switch (e.code) {
        //                         case "Enter":
        //                         case "Space":
        //                             switchSelect(li);
        //                             break;
        //                         case "ArrowUp":
        //                             e.preventDefault();
        //                             if (li !== ul.firstChild)
        //                                 li.previousElementSibling.focus();
        //                             else field.input[0].focus();
        //                             break;
        //                         case "ArrowDown":
        //                             e.preventDefault();
        //                             if (li !== ul.lastChild)
        //                                 li.nextElementSibling.focus();
        //                             break;
        //                         case "Tab":
        //                             fadeOut(ul);
        //                     }
        //                 });
        //                 ul.append(li);
        //             }
        //             if (!field.input[0].value) {
        //                 const first = ul.getElementsByTagName("li")[0];
        //                 first.className = "selected";
        //                 field.selected = first.getAttribute("data-select");
        //                 field.input[0].value =
        //                     first.getElementsByTagName("span")[0].textContent;
        //             }
        //             // field.input[0].addEventListener("focus", () => {
        //             //     fadeIn(ul, {
        //             //         dropdown: ul.closest(".field"),
        //             //     });
        //             //     ul.children[0].focus();
        //             // });
        //             // field.input[0].addEventListener("click", () => {
        //             //     fadeIn(ul, {
        //             //         dropdown: ul.closest(".field"),
        //             //     });
        //             //     ul.children[0].focus();
        //             // });
        //             // ul.addEventListener("keydown", (e) => {
        //             //     switch (e.code) {
        //             //         case "Escape":
        //             //             fadeOut(ul);
        //             //     }
        //             // });
        //         }
        //     }
        // } else if (data.f === 12 && data.response.id)
        //     Field.fields[data.x].addSelectize(data.response.id, data.t);
    }
    selectizeKeysNav(e) {
        // modifier en function pour la navigation globale dans le site, avec raccourcis clavier (n= jump to navbar, t=jump to topbar, ...)
        const target = e.currentTarget;
        switch (e.code) {
            case "ArrowUp":
                e.preventDefault();
                // if (chat.wrapper.contains(target)) {
                //     target.nextSibling?.focus();
                // } else {
                target.previousSibling
                    ? target.previousSibling.focus()
                    : this.input[0].focus();
                // }
                break;
            case "ArrowDown":
                e.preventDefault();
                // if (chat.wrapper.contains(target)) {
                //     target.previousSibling
                //         ? target.previousSibling.focus()
                //         : this.input[0].focus();
                // } else {
                target.nextSibling?.focus();
                // }
                break;
            case "Enter":
            case "Space":
                // if (
                //     chat.wrapper.contains(target) &&
                //     !target.classList.contains("offline")
                // )
                //     chat.request(parseInt(target.getAttribute("data-select")));
                // else if (!chat.wrapper.contains(target)) {
                let arr = [];
                Array.from(target.getElementsByTagName("span")).map((e) =>
                    arr.push(e.textContent)
                );
                this.addSelectize(
                    target.getAttribute("data-select"),
                    arr.join(" ")
                );
                // }
                break;
            case "Escape":
                e.preventDefault();
                fadeOut(this.ul);
                this.input[0].blur();
        }
    }
    static updateFields() {
        // for each object in array Field.fields
        for (const field of Field.fields) {
            // if wrapper doesn't exist anymore, remove object from array
            if (typeof field.wrapper === "undefined") {
                Field.fields.splice(Field.fields.indexOf(field), 1);
            }
        }
    }
}
