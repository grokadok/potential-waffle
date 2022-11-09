const is_explorer = navigator.userAgent.indexOf("MSIE") > -1,
    is_firefox = navigator.userAgent.indexOf("Firefox") > -1,
    is_opera = navigator.userAgent.toLowerCase().indexOf("op") > -1;
let is_chrome = navigator.userAgent.indexOf("Chrome") > -1,
    is_safari = navigator.userAgent.indexOf("Safari") > -1;
if (is_chrome && is_safari) {
    is_safari = false;
}
if (is_chrome && is_opera) {
    is_chrome = false;
}

/**
 * Appends several elements to parent
 * @param {HTMLElement} parent
 * @param {HTMLElement[]} children
 * @deprecated use element.append() instead
 */
function appendChildren(parent, children) {
    for (const child of children) {
        parent.appendChild(child);
    }
}
/**
 * Compares two arrays, and returns elements presents in only one of them.
 * @param {Iterable} array_1
 * @param {Iterable} array_2
 * @returns
 */
function arrayCompare(array_1, array_2) {
    let only_1 = [],
        only_2 = [],
        both = [],
        equal = true;
    for (el of array_1) {
        if (array_2.includes(el)) both.push(el);
        else {
            only_1.push(el);
            equal = false;
        }
    }
    for (el of array_2)
        if (!array_1.includes(el)) {
            only_2.push(el);
            equal = false;
        }
    return {
        only_1: only_1,
        only_2: only_2,
        both: both,
        equal: equal,
    };
}
/**
 * Adds class "blurred" (filter:blur(2px) grayscale(.6);pointer-events:none) to element(s).
 * @param {(HTMLElement|HTMLElement[])} el - Element(s) to blur.
 */
function blurElements(el) {
    const action = (el) => el.classList.add("blurred");
    if (Array.isArray(el)) {
        for (let e of el) action(e);
    } else action(el);
}
/**
 * Capitalize first letter of provided string's words.
 * @param {String} str
 * @returns {String}
 */
function capitalize(str) {
    let array = [];
    for (const s of str.split(" ")) {
        array.push(s.charAt().toUpperCase() + s.slice(1));
    }
    return array.join(" ");
}
/**
 * Clones and replaces elements (e.g. to get rid of eventlisteners).
 * @param {HTMLElement|HTMLElement[]} el - Element or array of elements to clone & replace
 * @returns {HTMLElement|HTMLElement[]} Element or array of elements freshly cloned.
 */
function cloneAndReplace(el) {
    let newEl;
    if (Array.isArray(el)) {
        newEl = [];
        for (const e of el) {
            newEl.push(e.parentNode.replaceChild(e.cloneNode(true), e));
        }
    } else {
        newEl = el.parentNode.replaceChild(el.cloneNode(true), el);
    }
    return newEl;
}
/**
 * Add class "up" to element.
 * @param {HTMLElement} el - Element to add "up" to.
 */
function commentUp(el) {
    el.classList.add("up");
}
/**
 * Removes class "up" to element(s).
 * @param {(HTMLElement|HTMLElement[])} el - Element or array of elements which will lose "up"
 */
function commentDown(el) {
    Array.isArray(el)
        ? el.forEach((e) => e.classList.remove("up"))
        : el.classList.remove("up");
}
function convertHexToRGB(hex) {
    let r,
        g,
        b,
        a = "";
    if (hex === "") hex = "000000";
    if (hex.charAt(0) === "#") hex = hex.substring(1, hex.length);
    if (hex.length !== 6 && hex.length !== 8 && hex.length !== 3) {
        alert("Please enter 6 digits color code !");
        return;
    }
    if (hex.length === 3) {
        r = hex.substring(0, 1);
        g = hex.substring(1, 2);
        b = hex.substring(2, 3);
        r = r + r;
        g = g + g;
        b = b + b;
    } else {
        r = hex.substring(0, 2);
        g = hex.substring(2, 4);
        b = hex.substring(4, 6);
    }
    if (hex.length === 8) {
        a = hex.substring(6, 8);
        a = (parseInt(a, 16) / 255.0).toFixed(2);
    }
    r = parseInt(r, 16);
    g = parseInt(g, 16);
    b = parseInt(b, 16);
    let css = "rgb(" + r + ", " + g + ", " + b + ")";
    if (hex.length == 8)
        css = "rgba(" + r + ", " + g + ", " + b + ", " + a + ")";
    return css;
}
/**
 * Converts rem value into pixel value.
 * @param {Number} rem
 * @returns
 */
function convertRemToPixels(rem) {
    return (
        rem * parseFloat(getComputedStyle(document.documentElement).fontSize)
    );
}
/**
 * Convert a simple int into a hour string.
 * @param {Number} int
 */
function convertIntToHour(int) {
    if (int < 24) {
        let hour = `${int}:00`;
        if (int < 10) hour = `0${hour}`;
        return hour;
    } else {
        console.warn("Integer must be < 24");
        return false;
    }
}
/**
 * Returns date with time rounded to closest quarter.
 * @param {Date} date
 * @returns
 */
function dateGetClosestQuarter(date) {
    const minutes = date.getMinutes(),
        hours = date.getHours(),
        m = ((((minutes + 7.5) / 15) | 0) * 15) % 60,
        h = (((minutes / 105 + 0.5) | 0) + hours) % 24;
    return new Date(date.getFullYear(), date.getMonth(), date.getDate(), h, m);
}
/**
 * Sets disabled=true to element(s).
 * @param {HTMLElement|HTMLElement[]} el - Element or array of elements to be disabled.
 */
function disable(el) {
    const action = (el) => (el.disabled = true);
    Array.isArray(el) ? el.forEach((x) => action(x)) : action(el);
}
/**
 * Simple timer to enable elements after a short time (e.g. to avoid mistype)
 * @param {HTMLElement|HTMLElement[]} el
 */
function elEnableTimer(el) {
    const action = (el) => {
        if (el.hidden === false)
            setTimeout(function () {
                enable(el);
            }, 20);
    };
    if (Array.isArray(el)) for (const e of el) action(e);
    else action(el);
}
/**
 * Adds classes "hidden" & "loading" and sets textContent="" to element(s).
 * @param {(HTMLElement|HTMLElement[])} el - Element or array of elements to be emptied.
 */
function emptyEl(el) {
    if (Array.isArray(el)) {
        for (const e of el) {
            e.classList.add("hidden", "loading");
            e.textContent = "";
        }
    } else {
        el.classList.add("hidden", "loading");
        el.textContent = "";
    }
}
/**
 * Resets previously loaded modal, keeping it's elements but emptying them.
 * @param {HTMLElement} el - Modal element.
 */
function emptyModal(el) {
    let modalFields = Array.from(el.getElementsByTagName("input")).concat(
            Array.from(el.getElementsByTagName("textarea"))
        ),
        modalSelected = Array.from(
            el.getElementsByClassName("selectize-selected")
        );
    resetFields(modalFields);
    removeChildren(modalSelected, true);
}
/**
 * Sets disabled=false to element.
 * @param {HTMLElement} el - Element to be enabled.
 */
function enable(el) {
    const action = (el) => (el.disabled = false);
    Array.isArray(el) ? el.forEach((x) => action(x)) : action(el);
}
/**
 * Removes class "fadeout" & "hidden"
 * @param {(HTMLElement|HTMLElement[])} el - The element(s) to fade in.
 * @param {Object} options - hide:boolean, dropdown:HTMLElement
 */
function fadeIn(el, options) {
    const action = (el) => {
        el.hidden = false;
        el.disabled = false;
        if (el.getElementsByTagName("input").length > 0)
            enable(el.getElementsByTagName("input")[0]);
        if (el.id === "phone-container") {
            el.getElementsByClassName("iti__selected-flag")[0].setAttribute(
                "tabindex",
                "0"
            );
        }
        setTimeout(el.classList.remove("fadeout"), 50);
        if (options && options["dropdown"]) {
            hideOnClickOutside(el, el.closest(".field"));
        }
    };
    if (Array.isArray(el)) {
        for (let e of el) action(e);
    } else {
        action(el);
    }
}
/**
 * Fades out (margin,padding,height,opacity:0 & pointer-events:none) and optionnaly hides (display=none) element(s)
 * @param {(HTMLElement|HTMLElement[])} el - The element or array of elements to fade out.
 * @param {Object} [options]
 * @param {Boolean} [options.hide] - Adds hidden=true.
 */
function fadeOut(el, options) {
    const action = (el) => {
        el.disabled = true;
        el.classList.add("fadeout");
        if (el.getElementsByTagName("input").length > 0)
            disable(el.getElementsByTagName("input")[0]);
        if (typeof options !== "undefined" && options["hide"] === true) {
            setTimeout(() => {
                el.hidden = true;
            }, 600);
        }
        if (el.id === "phone-container") {
            el.getElementsByClassName("iti__selected-flag")[0].removeAttribute(
                "tabindex"
            );
        }
    };
    if (Array.isArray(el)) {
        for (let e of el) {
            action(e);
        }
    } else {
        action(el);
    }
}
/**
 * Sets interval of 50ms between requests on input change of element, then proceeds to the request.
 * @param {HTMLElement} el
 * @param {Object} type
 */
function fetchDataTimer(el, type) {
    clearTimeout(timer);
    if (type.selectize !== undefined) {
        if (el.value) {
            fetchIt();
        } else {
            clearTimeout(timer);
            let ul = el.parentNode.getElementsByTagName("ul")[0];
            fadeOut(ul);
            removeChildren(ul, true);
        }
    }
    function fetchIt() {
        timer = setTimeout(() => {
            fetchSelectizeData(el, type.selectize);
        }, 50);
    }
}
function focusNextElement() {
    //add all elements we want to include in our selection
    const focussableElements =
        'a:not([disabled],.fadeout,[hidden]), button:not([disabled],.fadeout,[hidden]), input[type=text]:not([disabled],.fadeout,[hidden]), [tabindex]:not([disabled],.fadeout,[hidden]):not([tabindex="-1"])';
    if (document.activeElement && document.activeElement.form) {
        const focussable = Array.prototype.filter.call(
            document.activeElement.form.querySelectorAll(focussableElements),
            function (element) {
                //check for visibility while always include the current activeElement
                return (
                    element.offsetWidth > 0 ||
                    element.offsetHeight > 0 ||
                    element === document.activeElement
                );
            }
        );
        let index = focussable.indexOf(document.activeElement);
        if (index > -1) {
            const nextElement = focussable[index + 1] || focussable[0];
            nextElement.focus();
            console.warn(`focused ${nextElement}`);
        }
    }
}
/**
 * Returns array of days' dates from start to end.
 * @param {Date} start
 * @param {Date} end
 * @return {Date[]} days
 */
function getDaysBetweenDates(start, end) {
    let current = new Date(
            start.getFullYear(),
            start.getMonth(),
            start.getDate()
        ),
        days = [];
    const endDate = new Date(end.getFullYear(), end.getMonth(), end.getDate());
    while (current <= endDate) {
        days.push(new Date(current.valueOf()));
        current.setDate(current.getDate() + 1);
    }
    return days;
}
/**
 * Returns duration between start & end in minutes
 * @param {Date} start
 * @param {Date} end
 */
function getMinutesBetweenDates(start, end) {
    return (end.valueOf() - start.valueOf()) / 60000;
}
/**
 * Returns first day of date's week according to start of week.
 * @param {Date} date Date from which to return first day of week.
 * @param {Number} [weekstart] Optional week start, default = 1 (monday).
 * @returns Date object of first day of week.
 */
function getFirstDayOfWeek(date, weekstart = 1) {
    let firstDay = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate()
    );
    daysMap = [0, 1, 2, 3, 4, 5, 6];
    daysMap
        .filter((x) => x < weekstart)
        .map(() => daysMap.push(daysMap.shift()));
    firstDay.setDate(firstDay.getDate() - daysMap.indexOf(firstDay.getDay()));
    return firstDay;
}
/**
 * Returns last day of provided date week.
 * @param {Date} date Date from which to return last day of week.
 * @param {Number} [weekstart] Optional week start, default = 1 (monday).
 * @returns {Date} Date object of last day of week.
 */
function getLastDayOfWeek(date, weekstart = 1) {
    let lastDay = getFirstDayOfWeek(date, weekstart);
    lastDay.setDate(lastDay.getDate() + 6);
    return lastDay;
}
/**
 * Returns an array of local months
 * @param {String} type - 'numeric', '2-digit', 'long', 'short', 'narrow'
 * @returns Months array
 */
function getLocalMonths(type = "long") {
    let date = new Date(),
        months = [];
    for (let i = 0; i < 12; i++) {
        date.setMonth(i);
        months.push([i, date.toLocaleDateString("en-us", { month: type })]);
    }
    return months;
}
/**
 * Returns an array of local weekdays from Monday or provided first day of week.
 * @param {Object} options
 * @param {Number} options.weekstart - First day of week (0: Sunday, 1: Monday,...), default: 1
 * @param {String} options.type - 'long', 'short', 'narrow'
 * @returns Local week days
 */
function getLocalWeekDays(options) {
    const firstDay = getFirstDayOfWeek(new Date(), options?.weekstart ?? 1),
        dayNumber = firstDay.getDay();
    let days = [
        [
            dayNumber,
            firstDay.toLocaleString(navigator.language, {
                weekday: options?.type ?? "long",
            }),
        ],
    ];
    for (let i = 0; i < 6; i++) {
        firstDay.setDate(firstDay.getDate() + 1);
        const dayNumber = firstDay.getDay();
        days.push([
            dayNumber,
            firstDay.toLocaleString(navigator.language, {
                weekday: options?.type ?? "long",
            }),
        ]);
    }
    return days;
}
/**
 * Returns week number for a given date.
 * @param {Date} date
 * @returns {Number} week number
 */
function getWeekNumber(date) {
    let tdt = new Date(date.valueOf()),
        dayNumber = (date.getDay() + 6) % 7;
    tdt.setDate(tdt.getDate() - dayNumber + 3);
    const firstThursday = tdt.valueOf();
    tdt.setMonth(0, 1);
    if (tdt.getDay() !== 4) {
        tdt.setMonth(0, 1 + ((4 - tdt.getDay() + 7) % 7));
    }
    return 1 + Math.ceil((firstThursday - tdt) / 604800000);
}
/**
 * Adds an eventlistener on document to fadeOut element on click outside of itself or it's ancestor.
 * @param {HTMLElement} el - Element to fadeOut on click.
 * @param {HTMLElement} [anc] - Optional ancestor, defaults to el itself.
 */
function hideOnClickOutside(el, anc) {
    const ancestor = anc ?? el,
        outsideClickListener = (event) => {
            if (
                !ancestor.contains(event.target) &&
                !el.classList.contains("fadeout")
            ) {
                fadeOut(el);
                removeClickListener();
            }
        };
    function removeClickListener() {
        document.removeEventListener("click", outsideClickListener);
    }
    document.addEventListener("click", outsideClickListener);
}
/**
 * Highlights (unstyled <mark>) searched text in element(s).
 * @param {HTMLElement|HTMLElement[]} el - Element or array of elements to apply text highlight.
 * @param {String|String[]} needle - String or array of strings to highlight.
 */
function highlightSearch(el, needle) {
    if (Array.isArray(needle)) {
        needle = needle
            .map((x) => normalizePlus(x))
            .filter((x) => {
                return x !== "";
            })
            .join("|");
    }
    const regex = new RegExp(`((^|\\b|\\w+)(${needle})($|\\b|\\w+))`, "gi"),
        textReplace = (e) => {
            e.innerHTML = e.innerHTML.replace(/(<mark>|<\/mark>)/gim, "");
            e.innerHTML = e.textContent.replace(regex, "<mark>$&</mark>");
        };
    // const regex = new RegExp(needle, "gi");
    if (Array.isArray(el)) {
        for (let e of el) textReplace(e);
    } else textReplace(el);
}
/**
 * Parses ical string into object, adding uid at first level (remove it before converting back to string).
 * @param {String} ical
 * @returns
 */
function icalToObject(ical) {
    // !!!!!!!!
    // WONT WORK WITH MULTIPLE OF OBJECT TYPES (e.g. more than one VALARM)
    // !!!!!!!!
    // find separator
    const separator = ical.includes("\r\n") ? "\r\n" : "\n";
    // set cursor
    let icalObject = {},
        cursor = [],
        insertPoint;
    // for each line
    for (const cal of ical.split(separator).filter((x) => x !== "")) {
        const val = cal.split(":");
        switch (val[0]) {
            case "BEGIN":
                // store property object
                insertPoint = icalObject;
                for (let i = cursor.length - 1; i >= 0; i--)
                    insertPoint = insertPoint[cursor[i]];
                insertPoint[val[1]] = {};
                cursor.unshift(val[1]);
                break;
            case "END":
                cursor.shift();
                break;
            default:
                // store property value
                if (val[0] === "UID") icalObject.uid = val[1];
                insertPoint = icalObject;
                for (let i = cursor.length - 1; i >= 0; i--)
                    insertPoint = insertPoint[cursor[i]];
                insertPoint[val[0]] = val[1];
        }
    }
    return icalObject;
}
/**
 * Removes class "valid", adds class "invalid" to element.
 * @param {HTMLElement} el - Element to be invalidated
 */
function invalidate(el) {
    el.classList.contains("valid")
        ? el.classList.replace("valid", "invalid")
        : el.classList.add("invalid");
}
/**
 * Adds class "loading" and removes class "hidden" to element(s).
 * @param {(HTMLElement|HTMLElement[])} el - Element or array of elements to load in.
 */
function loadIn(el) {
    if (Array.isArray(el)) {
        for (let i = 0; i < el.length; i++) {
            el[i].classList.add("loading");
            el[i].classList.remove("hidden");
        }
    } else {
        el.classList.add("loading");
        el.classList.remove("hidden");
    }
}
/**
 * Removes class "loading" to element(s).
 * @param {(HTMLElement|HTMLElement[])} el - The element or array of elements to load out.
 */
function loadOut(el) {
    if (Array.isArray(el)) {
        for (let i = 0; i < el.length; i++) {
            el[i].classList.remove("loading");
        }
    } else {
        el.classList.remove("loading");
    }
}
/**
 * Normalize string, removes accents, trims spaces, locale lowercase.
 * @param {String} string
 * @returns normalized string
 */
function normalizePlus(string) {
    return string
        .trim()
        .toLocaleLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
}
/**
 * Parse calendar object to valid ical string (sortof).
 * @param {Object} cal - ical object generated with icalToObject().
 */
function objectToIcal(cal) {
    let ical = "";
    const action = (key, value) => {
        if (typeof value === "string") ical += `${key}:${value}\n`;
        else {
            ical += `BEGIN:${key}\n`;
            for (const [k, v] of Object.entries(value)) action(k, v);
            ical += `END:${key}\n`;
        }
    };
    for (const [key, value] of Object.entries(cal)) action(key, value);
    return ical;
}
function objIsEmpty(obj) {
    let state = true;
    for (const prop in obj) return (state = false);
    return state;
}
/**
 * Refresh data from tabulators from element.
 * @param {HTMLElement} el - Parent containing tabulators to refresh.
 * @param {[Number]} [t] - Array of data-task to select which tables to refresh, or all tables by omitting it.
 */
function refreshTabData(el, t) {
    const tables = Tabulator.findTable(".tabulator");
    if (tables) {
        for (const table of tables) {
            const task = table["element"]
                .closest("fieldset")
                .getAttribute("data-t");
            if (
                (!t || t.includes(parseInt(task))) &&
                el.contains(table["element"])
            ) {
                const message = {
                    f: parseInt(6),
                    t: parseInt(task),
                };
                socket.send(message);
            }
        }
    }
}
/**
 * Removes attributes from element.
 * @param {HTMLElement} el - Element to be cleaned.
 * @param {String[]} [att] - Attributes to be removed, if not set, removes all attributes.
 */
function removeAttributes(el, att) {
    if (typeof att !== "undefined") {
        for (const a of att) {
            el.removeAttribute(a);
        }
    } else {
        let a = [];
        for (let i = 0; i < el.attributes.length; i++) {
            let b = el.attributes[i].name;
            if (b != "class") a.push(b);
        }
        a.forEach((c) => el.removeAttribute(c));
    }
}
/**
 * Removes all but first child from element, removes it too if all = true.
 * @param {HTMLElement} el - Element whose options will be removed
 * @param {Boolean} [all=false] - false: removes all but first, true: removes all
 */
function removeChildren(el, all = false) {
    action = (e) => {
        let i,
            L = e.children.length - 1,
            a = all === false ? 0 : -1;
        for (i = L; i > a; i--) {
            e.children[i].remove();
        }
    };

    if (Array.isArray(el)) {
        for (let e of el) {
            action(e);
        }
    } else {
        action(el);
    }
}
/**
 * Removes all but first option from element, removes it too if all = true.
 * @param {HTMLElement} el - Element whose options will be removed
 * @param {Boolean} [all=false] - false: removes all but first, true: removes all
 */
function removeOptions(el, all = false) {
    let i,
        L = el.options.length - 1,
        a = all === false ? 0 : -1;
    if (L >= 0) {
        for (i = L; i > a; i--) {
            el.options[i].remove();
        }
    }
}
/**
 * Resets value of element(s).
 * @param {(HTMLElement|HTMLElement[])} el - The element or array of elements to reset.
 */
function resetFields(el) {
    if (Array.isArray(el)) {
        for (let i = 0; i < el.length; i++) {
            el[i].value = "";
        }
    } else el.value = "";
}
/**
 * Resets value of all input elements.
 */
function resetAllFields() {
    Array.from(document.getElementsByTagName("input")).forEach(
        (e) => (e.value = "")
    );
}
/**
 * Removes all elements from modal except title and the footer, removes attributes.
 * @param {HTMLElement} el - Element (modal) to reset.
 * @returns {HTMLElement} - Reseted modal
 */
function resetModal(el) {
    tabuDestroy(el);
    removeAttributes(el);
    el.getElementsByTagName("h2")[0].textContent = "";
    for (let i = 1; el.children.length > 2; ) {
        el.children[i].remove();
    }
    return el;
}
/**
 * Selects text inside element if possible.
 * @param {HTMLElement} node
 */
function selectText(node) {
    if (document.body.createTextRange) {
        const range = document.body.createTextRange();
        range.moveToElementText(node);
        range.select();
    } else if (window.getSelection) {
        const selection = window.getSelection(),
            range = document.createRange();
        range.selectNodeContents(node);
        selection.removeAllRanges();
        selection.addRange(range);
    } else {
        console.warn("Could not select text in node: Unsupported browser.");
    }
}
/**
 * Set attributes from array to element.
 * @param {HTMLElement} el - Element to apply attributes to
 * @param {Array} att - Array of attributes to apply to element
 */
function setElementAttributes(el, att) {
    for (let [key, value] of Object.entries(att))
        if (typeof value === "number" || value) el.setAttribute(key, value);
}
/**
 * Sets an element draggable.
 * @param {HTMLElement} el
 * @param {Object} [param]
 * @param {Boolean} [param.constrain=false] If true, constrains to offsetParent.
 * @param {Boolean} [param.magnet=false] If true, hop to border if close enough, and set corresponding class.
 * @param {Boolean} [param.parent] Sets the parent element to constrain and/or magnetize the element in, default if offsetParent.
 */
function setElementDraggable(el, param) {
    if (
        el.style.position === "absolute" ||
        getComputedStyle(el).position === "absolute"
    ) {
        el.classList.add("drag");
        // variables for variant using translate:transform() :
        // let translateX = 0,
        //     translateY = 0;

        el.onpointerdown = function (event) {
            if (
                ["div", "ul", "li", "nav"].includes(
                    event.target.tagName.toLowerCase()
                )
            ) {
                const elRect = el.getBoundingClientRect(),
                    paRect = param?.parent
                        ? param.parent.getBoundingClientRect()
                        : el.offsetParent.getBoundingClientRect(),
                    elShiftX = event.clientX - elRect.left,
                    elShiftY = event.clientY - elRect.top,
                    paShiftX = paRect.x,
                    paShiftY = paRect.y;
                el.classList.add("up");
                el.classList.remove("left", "right", "top", "bottom");

                function moveAt(pageX, pageY) {
                    el.classList.remove("left", "right", "top", "bottom");
                    let x, y;
                    if (paRect && param?.constrain) {
                        const calcX = pageX - elShiftX - paShiftX,
                            calcY = pageY - elShiftY - paShiftY,
                            parentRight =
                                paRect.right - el.offsetWidth - paShiftX,
                            parentBottom =
                                paRect.bottom - el.offsetHeight - paShiftY;
                        if (calcX < 0) x = 0;
                        else if (calcX > parentRight) x = parentRight;
                        else x = calcX;
                        if (calcY < 0) y = 0;
                        else if (calcY > parentBottom) y = parentBottom;
                        else y = calcY;
                    } else {
                        x = pageX - elShiftX - paShiftX;
                        y = pageY - elShiftY - paShiftY;
                    }
                    if (param?.magnet) {
                        if (pageX - paRect.left < 100) {
                            x = 0;
                            el.classList.add("left");
                        }
                        // if boxRight - parentRight < 75, x=parentRight-boxWidth
                        if (paRect.right - pageX < 100) {
                            x = paRect.right - el.offsetWidth;
                            el.classList.add("right");
                        }
                        // if y - parentY < 75, y = parentTop
                        if (pageY - paRect.top < 100) {
                            y = 0;
                            el.classList.add("top");
                        }
                        // if boxBottom - parentBottom < 75, y = parentBottom - boxHeight
                        if (paRect.bottom - pageY < 100) {
                            y = paRect.bottom - el.offsetHeight;
                            el.classList.add("bottom");
                        }
                    }
                    el.style.left = x + "px";
                    el.style.top = y + "px";
                }

                // variant using transform:translate(), but doesn't seem to perform as well as using top/left;
                // function moveAt(movementX, movementY) {
                //     translateX += movementX;
                //     translateY += movementY;
                //     el.style.transform = `translate(${translateX}px, ${translateY}px)`;
                // }
                // function onPointerMove(event) {
                //     // console.log(event);
                //     moveAt(event.movementX, event.movementY);
                // }

                function onPointerMove(event) {
                    event.preventDefault();
                    moveAt(event.pageX, event.pageY);
                }
                moveAt(event.pageX, event.pageY);
                // move the el on pointermove
                document.addEventListener("pointermove", onPointerMove);

                const release = (el) => {
                    el.classList.remove("up");
                    if (param?.magnet) {
                        if (
                            el.classList.contains("left") ||
                            el.classList.contains("right")
                        )
                            el.style.left = "";
                        if (
                            el.classList.contains("top") ||
                            el.classList.contains("bottom")
                        ) {
                            el.style.top = "";
                            el.style.left = "";
                        }
                    }
                };
                // drop the el, remove listeners.
                document.addEventListener(
                    "pointerup",
                    () => {
                        document.removeEventListener(
                            "pointermove",
                            onPointerMove
                        );
                        release(el);
                    },
                    { once: true }
                );
                document.body.addEventListener(
                    "pointerleave",
                    () => {
                        document.removeEventListener(
                            "pointermove",
                            onPointerMove
                        );
                        release(el);
                    },
                    { once: true }
                );
            }
        };

        el.ondragstart = () => {
            return false;
        };
    } else
        return msg.new({
            content: "Element must have position set to absolute.",
            type: "danger",
        });
}
/**
 * Sets element resizable, duh.
 * @param {HTMLElement} el
 * @param {String[]} side - From which side(s) the element is resizable.
 */
function setElementResizable(el, side) {
    // to be dealt with.
}
/**
 *
 * @param {HTMLElement|HTMLElement[]} el
 */
function setElementSelectable(el) {
    const action = (e) => {
        e.setAttribute("tabindex", "0");
    };
    Array.isArray(el) ? el.map((x) => action(x)) : action(el);
}
/**
 * Destroys tabulator(s) from element(s).
 * @param {HTMLElement|HTMLElement[]} el - Element(s) from which every tabulator will be destroyed.
 */
function tabuDestroy(el) {
    let tables = Tabulator.findTable(".tabulator");
    const action = (node) => {
        for (const table of tables) {
            if (node.contains(table["element"])) table.destroy();
        }
    };
    if (tables) {
        if (Array.isArray(el)) {
            for (const e of el) {
                action(e);
            }
        } else action(el);
    }
}
/**
 * Format a date string to a string accepted by SimpleCalDAVClient.
 * @param {String} date
 * @returns {String|false} String in the format yyyymmddThhmmssZ
 */
function toCalDAVString(date) {
    try {
        return (
            new Date(date).toISOString().replace(/:|-/g, "").slice(0, -5) + "Z"
        );
    } catch (e) {
        console.error(e);
        return false;
    }
}
/**
 *  Returns valid string to use as date input's value.
 * @param {Date} date
 */
function toHTMLInputDateValue(date) {
    let tempDate = new Date(date);
    // convert to timezone
    tempDate.setMinutes(tempDate.getMinutes() - tempDate.getTimezoneOffset());
    return tempDate.toISOString().slice(0, 10);
}
/**
 *  Returns valid string to use as datetime-local input's value.
 * @param {Date} date
 */
function toHTMLInputDateTimeValue(date) {
    let tempDate = new Date(date);
    // convert to timezone
    tempDate.setMinutes(tempDate.getMinutes() - tempDate.getTimezoneOffset());
    return tempDate.toISOString().slice(0, 16);
}
/**
 * Convert compact ISO8601 string to extended for fullcalendar.
 * @param {String} date
 */
function toISO8601ExtString(date) {
    date = date.slice(0, 4) + "-" + date.slice(4, 6) + "-" + date.slice(6);
    if (date.length > 10)
        date =
            date.slice(0, 13) + ":" + date.slice(13, 15) + ":" + date.slice(15);
    return date;
}
/**
 * Returns local month from Date month number.
 * @param {Number} number Month number (0: jan)
 * @param {String} type "numeric", "2-digit", "long", "short", "narrow"
 * @returns {String} Local month
 */
function toLocalMonth(number, type = "long") {
    let date = new Date(2022, number);
    return date.toLocaleString(navigator.language, {
        month: type,
    });
}
/**
 * Returns local week day from Date weekday number (0: Sunday)
 * @param {Number} number Day number to get string of
 * @param {String} type "long", "short", "narrow"
 * @returns {String} Local weekday.
 */
function toLocalWeekDay(number, type = "long") {
    let date = new Date();
    while (date.getDay() !== number) date.setDate(date.getDate() + 1);
    return date.toLocaleString(navigator.language, {
        weekday: type,
    });
}
/** Format date to mysql datetime.
 * @param {Date} date
 */
function toMYSQLDTString(date) {
    return date.toISOString().slice(0, 19).replace("T", " ");
}
/**
 * Toggles between classes (useful when wanting to toggle between more than two classes)
 * @param {HTMLElement} el
 * @param {Object} options
 * @param {Array} options.classes - Classes to loop through
 * @param {Boolean} [options.none] - Insert in the loop the 'no class' state
 */
function toggleClasses(el, options) {
    for (const cla of options.classes) {
        if (el.classList.contains(cla)) {
            const index = options.classes.indexOf(cla);
            el.classList.remove(cla);
            if (index + 1 === options.classes.length && !options.none) {
                el.classList.add(options.classes[0]);
            } else if (index + 1 < options.classes.length)
                el.classList.add(options.classes[index + 1]);
            return;
        }
    }
    options.none
        ? el.classList.add(options.classes[0])
        : console.error(
              "toggleClasses: no class present on element and none:false."
          );
}
/**
 * Switches attribute "type" of element between "password" & "text"
 * @param {HTMLElement} el - The element which type will be changed.
 */
function togglePw(el) {
    const a = el.getElementsByTagName("input")[0],
        b = a.getAttribute("type"),
        c = el.getElementsByTagName("svg")[0].getElementsByTagName("path");
    if (b === "password") {
        a.setAttribute("type", "text");
        c[0].classList.add("hide");
        c[1].classList.remove("hide");
    } else if (b === "text") {
        a.setAttribute("type", "password");
        c[0].classList.remove("hide");
        c[1].classList.add("hide");
    }
}
/**
 * Returns a date YYYYMMDD string from Date object.
 * @param {Date} date
 */
function toYYYYMMDDString(date) {
    const d = date.getDate();
    return `${date.getFullYear()}${date.getMonth()}${d < 10 ? "0" + d : d}`;
}
/**
 * Removes class "blurred" from element(s).
 * @param {(HTMLElement|HTMLElement[])} el - The element or array of elements to unblur.
 */
function unblurElements(el) {
    const action = (el) => el.classList.remove("blurred");
    if (Array.isArray(el)) {
        for (const e of el) action(e);
    } else {
        action(el);
    }
}
/**
 * Unsets draggable to element, duh.
 * @param {HTMLElement} el
 */
function unsetElementDraggable(el) {
    el.onmousedown = null;
    el.classList.remove("drag", "up");
}
/**
 * Adds class "valid" and removes class "invalid" from element.
 * @param {HTMLElement} el - Element to validate.
 */
function validate(el) {
    el.classList.contains("invalid")
        ? el.classList.replace("invalid", "valid")
        : el.classList.add("valid");
}
