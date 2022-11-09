/**
 * Creates calendar in specified wrapper, with the ability to generate clones.
 */
class BopCal {
    static bopcals = [];
    static fullCalendars = [];
    static calendars = {};
    static events = [];
    /**
     *
     * @param {HTMLElement} element
     */
    constructor(element) {
        BopCal.bopcals.push(this);
        this.id = BopCal.bopcals.indexOf(this);
        this.controller = new AbortController();
        this.wrapper = element;
        this.wrapper.classList.add("bopcal");
        this.menu = document.createElement("div");
        this.menu.className = "menu";
        this.minical = {
            cal: document.createElement("div"),
            years: {},
            cursor: document.createElement("div"),
        };
        this.minical.cal.className = "mini";
        this.minical.cursor.className = "cursor";
        this.minical.cal.append(this.minical.cursor);
        this.bigcal = {
            cal: document.createElement("div"),
            layout: document.createElement("div"),
            wrapper: document.createElement("div"),
            years: {},
            info: document.createElement("div"),
        };
        this.bigcal.wrapper.className = "bigcal";
        this.bigcal.info.append(document.createElement("span"));
        this.bigcal.info.firstElementChild.textContent = "W##";
        this.bigcal.layout.append(document.createElement("div"));
        this.bigcal.wrapper.append(
            this.bigcal.layout,
            this.bigcal.info,
            this.bigcal.cal
        );
        this.toggle = document.createElement("button");
        this.toggle.textContent = "calendar";
        this.toggle.addEventListener("click", () => {
            this.bigcal.lock = false;
            this.bigcal.wrapper.classList.remove("locked");
            this.minicalFocus(new Date());
            this.toggle.blur();
            this.wrapper.classList.toggle("toggle");
        });
        this.weekstart = 1; // 0 = sunday
        // later get week info (start, weekend, etc.) according to locale browser settings
        // this.userLocale =
        //     navigator.languages && navigator.languages.length
        //         ? navigator.languages[0]
        //         : navigator.language;
        // get locale info from db for found userLocale.

        // layout
        // create 24 divs
        for (let i = 0; i < 24; i++) {
            let hour = document.createElement("div");
            hour.setAttribute("data-hour", convertIntToHour(i));
            this.bigcal.layout.firstElementChild.append(hour);
        }
        let isScrolling;
        const stopScrolling = () => {
            const x =
                    this.bigcal.info.offsetWidth +
                    this.bigcal.wrapper.offsetLeft,
                y = this.bigcal.wrapper.offsetTop + this.wrapper.offsetTop;
            const element = document
                .elementFromPoint(x + 1, y + 1)
                .closest("[data-date]");
            if (element) {
                // set week number
                this.bigcal.info.firstElementChild.textContent = `W${element
                    .closest("[data-week]")
                    .getAttribute("data-week")}`;
                let date = new Date(
                    parseInt(
                        element
                            .closest("[data-month]")
                            .getAttribute("data-value")
                    )
                );
                date.setDate(element.getAttribute("data-date"));
                if (element.classList.contains("fade")) {
                    element.getAttribute("data-date") > 15
                        ? date.setMonth(date.getMonth() - 1)
                        : date.setMonth(date.getMonth() + 1);
                }
                this.minicalFocus(date);
            }
        };
        this.bigcal.cal.addEventListener("scroll", (e) => {
            this.bigcal.layout.firstElementChild.style.top = `-${this.bigcal.cal.scrollTop}px`;
            if (this.editor.wrapper.classList.contains("show"))
                this.editorHide();
            if (typeof this.bigcal.focus !== "undefined")
                delete this.bigcal.focus;
            // set timeout
            clearTimeout(isScrolling);
            isScrolling = setTimeout(() => {
                stopScrolling(
                    this.bigcal.cal.scrollLeft,
                    this.bigcal.cal.scrollTop
                );
            }, 100);
        });
        document.addEventListener("click", (e) => this.clickEvents(e), {
            signal: this.controller.signal,
        });
        this.bigcal.cal.addEventListener("dblclick", (e) => {
            // if day, create event on day, at time if view = week or day, else allday if month
            if (
                e.target.parentNode.getAttribute("data-date") &&
                e.target !== e.target.parentNode.firstElementChild
            ) {
                if (this.active) {
                    if (this.calendars[this.active].visible)
                        this.newEvent(this.getCursorDate(e));
                    else
                        msg.new({
                            type: "theme",
                            content:
                                "Le calendrier actif est caché, le révéler pour pouvoir créer un événement ?",
                            btn1listener: () => {
                                this.toggleCalVisibility(this.active);
                                msg.close();
                            },
                            btn1style: "success",
                            btn1text: "révéler",
                        });
                } else
                    msg.new({
                        type: "warning",
                        content:
                            "Aucun calendrier sélectionné pour la création d'un nouvel événement.",
                    });
            }
        });
        this.bigcal.cal.addEventListener("pointerdown", (e) => {
            // if target is event and event not focus, focus it
            if (e.target.closest("[data-uid]")) {
                const componentElement = e.target.closest("[data-uid]"),
                    idcal = componentElement.getAttribute("data-cal"),
                    uid = componentElement.getAttribute("data-uid"),
                    component = this.calendars[idcal].components[uid],
                    handleStart =
                        componentElement.getElementsByTagName("div")[0],
                    handleEnd = componentElement.getElementsByTagName("div")[1];
                if (this.focus && this.focus !== component) {
                    Object.values(this.focus.elements).forEach((x) =>
                        x.classList.remove("focus")
                    );
                    // this.focus.classList.remove("focus");
                }
                // componentElement.classList.add("focus");
                this.focus = component;
                Object.values(this.focus.elements).forEach((x) =>
                    x.classList.add("focus")
                );
                // move editor towards componentElement and fill with component's data;
                // this.editorFocus(idcal, uid, componentElement);

                if (e.target === handleStart) {
                    const cal = this,
                        limit = this.bigcal.cal.getBoundingClientRect(),
                        origin = [component.start, component.end];
                    let applied = false;
                    // on mouse drag
                    function onPointerMove(e) {
                        if (
                            e.clientX > limit.left &&
                            e.clientX < limit.right &&
                            e.clientY > limit.top &&
                            e.clientY < limit.bottom
                        ) {
                            const newDate = dateGetClosestQuarter(
                                cal.getCursorDate(e)
                            );
                            // if cursor date < end date
                            if (newDate < component.end) {
                                // set start date to cursor date
                                // apply to object
                                component.start = newDate;
                                // apply to element
                                cal.placeComponent(idcal, uid);
                            }
                        }
                    }
                    document.addEventListener("pointermove", onPointerMove);
                    document.addEventListener(
                        "pointerup",
                        () => {
                            document.removeEventListener(
                                "pointermove",
                                onPointerMove
                            );
                            if (
                                !applied &&
                                (component.start.valueOf() !==
                                    origin[0].valueOf() ||
                                    component.end.valueOf() !==
                                        origin[1].valueOf())
                            ) {
                                // apply to db
                                // send function, start/stop, uid, modified
                                cal.componentApplyRange(idcal, uid);
                                applied = true;
                            }
                        },
                        { once: true }
                    );
                    document.addEventListener(
                        "pointerleave",
                        () => {
                            document.removeEventListener(
                                "pointermove",
                                onPointerMove
                            );
                            if (
                                !applied &&
                                (component.start.valueOf() !==
                                    origin[0].valueOf() ||
                                    component.end.valueOf() !==
                                        origin[1].valueOf())
                            ) {
                                // apply to db
                                cal.componentApplyRange(idcal, uid);
                                applied = true;
                            }
                        },
                        { once: true }
                    );
                } else if (e.target === handleEnd) {
                    // on mouse drag
                    const cal = this,
                        limit = this.bigcal.cal.getBoundingClientRect(),
                        origin = [component.start, component.end];
                    let applied = false;
                    // on mouse drag
                    function onPointerMove(e) {
                        if (
                            e.clientX > limit.left &&
                            e.clientX < limit.right &&
                            e.clientY > limit.top &&
                            e.clientY < limit.bottom
                        ) {
                            const newDate = dateGetClosestQuarter(
                                cal.getCursorDate(e)
                            );
                            // if cursor date > start date
                            if (newDate > component.start) {
                                // set end date to cursor date
                                // apply to element
                                component.end = newDate;
                                // apply to element
                                cal.placeComponent(idcal, uid);
                            }
                        }
                    }
                    document.addEventListener("pointermove", onPointerMove);
                    document.addEventListener(
                        "pointerup",
                        () => {
                            document.removeEventListener(
                                "pointermove",
                                onPointerMove
                            );
                            if (
                                !applied &&
                                (component.start.valueOf() !==
                                    origin[0].valueOf() ||
                                    component.end.valueOf() !==
                                        origin[1].valueOf())
                            ) {
                                // apply to db
                                cal.componentApplyRange(idcal, uid);
                                applied = true;
                            }
                        },
                        { once: true }
                    );
                    document.addEventListener(
                        "pointerleave",
                        () => {
                            document.removeEventListener(
                                "pointermove",
                                onPointerMove
                            );
                            if (
                                !applied &&
                                (component.start.valueOf() !==
                                    origin[0].valueOf() ||
                                    component.end.valueOf() !==
                                        origin[1].valueOf())
                            ) {
                                // apply to db
                                cal.componentApplyRange(idcal, uid);
                                applied = true;
                            }
                        },
                        { once: true }
                    );
                } else {
                    let cal = this,
                        limit = this.bigcal.cal.getBoundingClientRect(),
                        applied = false,
                        origin = [component.start, component.end];
                    const clone = componentElement.cloneNode(),
                        duration = component.end - component.start,
                        offset =
                            e.clientY -
                            componentElement.getBoundingClientRect().y;
                    componentElement.classList.add("dragging"); // transparent element
                    clone.classList.add("clone", "hidden");
                    clone.style.outlineColor = clone.style.backgroundColor;
                    clone.style.backgroundColor = "";
                    componentElement.parentNode.append(clone);
                    function onPointerMove(e) {
                        // to avoid event start to jump to cursor
                        // change to add/remove time on Y
                        // change to add/remove day on X

                        e.preventDefault();
                        if (
                            e.clientX > limit.left &&
                            e.clientX < limit.right &&
                            e.clientY > limit.top &&
                            e.clientY < limit.bottom
                        ) {
                            const newDate = dateGetClosestQuarter(
                                cal.getCursorDate(e, offset)
                            );
                            if (
                                newDate.valueOf() !== component.start.valueOf()
                            ) {
                                component.start = newDate;
                                component.end = new Date(
                                    component.start.valueOf() + duration
                                );
                                clone.classList.remove("hidden");
                                cal.editorHide();
                                cal.placeComponent(idcal, uid, newDate);
                            }
                        }

                        // get Y to set hour if !=
                        // get target to set date if !=
                        // set component's new dates (new start + duration)
                        // place component accordingly
                    }
                    const release = () => {
                        componentElement.classList.remove("dragging");
                        clone?.remove();
                        if (
                            !applied &&
                            (component.start.valueOf() !==
                                origin[0].valueOf() ||
                                component.end.valueOf() !== origin[1].valueOf())
                        )
                            cal.componentApplyRange(idcal, uid);
                        applied = true;
                    };
                    document.addEventListener("pointermove", onPointerMove);
                    // on mouseup, remove clone, move original to pointer.target date pointerY time.
                    document.addEventListener(
                        "pointerup",
                        () => {
                            document.removeEventListener(
                                "pointermove",
                                onPointerMove
                            );
                            release();
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
                        },
                        { once: true }
                    );
                }
            }
        });

        // event editor
        this.editor = {
            modified: {},
            wrapper: document.createElement("div"),

            // input summary
            summary: document.createElement("input"),

            // date :
            date: {
                // grid 4 columns, auto rows
                wrapper: document.createElement("div"),
                // span summary
                summary: document.createElement("span"),
                sumUpdate: () => {
                    let sum = new Intl.DateTimeFormat("fr", {
                        year: "numeric",
                        month: "short",
                        day: "numeric",
                        hour: "numeric",
                        minute: "numeric",
                    }).formatRange(
                        new Date(this.editor.date.start.field.input[0].value),
                        new Date(this.editor.date.end.field.input[0].value)
                    );
                    this.editor.date.summary.textContent =
                        sum.search(/,.*,/) !== -1
                            ? sum.replace(" – ", "\n")
                            : sum;
                },
                // checkbox allday
                allday: {
                    input: document.createElement("input"),
                    span: document.createElement("span"),
                    wrapper: document.createElement("div"),
                    setAllday: () => {
                        delete this.editor.modified.all_day;
                        const allday = this.editor.date.allday.input.checked
                            ? 1
                            : 0;
                        if (this.focus.allday !== allday)
                            this.editor.modified.all_day = allday;
                        console.log(this.editor.modified);
                    },
                },
                // input start
                start: {
                    field: new Field({
                        compact: true,
                        max: new Date(),
                        name: "Start",
                        required: true,
                        type: "datetime",
                        value: new Date(),
                    }),
                    span: document.createElement("span"),
                    wrapper: document.createElement("div"),
                },
                setStart: (date) => {
                    if (
                        toHTMLInputDateTimeValue(date) !==
                        this.editor.date.start.field.input[0].value
                    )
                        this.editor.date.start.field.input[0].value =
                            toHTMLInputDateTimeValue(date);
                    this.editor.date.end.field.min(date);
                    if (!this.editor.date.start.field.input[0].validity.valid) {
                        let newEnd = new Date(date);
                        newEnd.setMinutes(newEnd.getMinutes() + 15);
                        this.editor.date.end.field.input[0].value =
                            toHTMLInputDateTimeValue(newEnd);
                        this.editor.date.start.field.max(newEnd);
                        this.editor.endRepeat.until.min(newEnd);
                        if (
                            !this.editor.endRepeat.until.input[0].validity.valid
                        )
                            this.editor.endRepeat.until.input[0].value =
                                toHTMLInputDateValue(newEnd);
                    }
                    this.editor.date.sumUpdate();
                    date.valueOf() !== this.focus.start.valueOf()
                        ? (this.editor.modified.start = toMYSQLDTString(date))
                        : delete this.editor.modified.start;
                },
                // input end
                end: {
                    field: new Field({
                        compact: true,
                        min: new Date(),
                        name: "End",
                        required: true,
                        type: "datetime",
                        value: new Date(),
                    }),
                    span: document.createElement("span"),
                    wrapper: document.createElement("div"),
                },
                setEnd: (date) => {
                    if (
                        toHTMLInputDateTimeValue(date) !==
                        this.editor.date.end.field.input[0].value
                    )
                        this.editor.date.end.field.input[0].value =
                            toHTMLInputDateTimeValue(date);
                    this.editor.date.start.field.max(date);
                    if (!this.editor.date.end.field.input[0].validity.valid) {
                        let newStart = date;
                        newStart.setMinutes(newStart.getMinutes() - 15);
                        this.editor.date.start.field.input[0].value =
                            toHTMLInputDateTimeValue(newStart);
                        this.editor.date.end.field.min(newStart);
                    }
                    this.editor.endRepeat.until.min(date);
                    if (!this.editor.endRepeat.until.input[0].validity.valid)
                        this.editor.endRepeat.until.input[0].value =
                            toHTMLInputDateValue(date);
                    this.editor.date.sumUpdate();
                    date.valueOf() !== this.focus.end.valueOf()
                        ? (this.editor.modified.end = toMYSQLDTString(date))
                        : delete this.editor.modified.end;
                },
            },

            // repeat :
            repeat: {
                summary: document.createElement("span"),
                sumUpdate: () => {
                    const value = parseInt(this.editor.repeat.preset.selected);
                    let summary = "";
                    switch (value) {
                        case 1:
                            summary = "Repeats daily";
                            break;
                        case 2:
                            summary = "Repeats weekly";
                            break;
                        case 3:
                            summary = "Repeats monthly";
                            break;
                        case 4:
                            summary = "Repeats yearly";
                            break;
                    }
                    if (value !== 5)
                        return (this.editor.repeat.summary.textContent =
                            summary);
                    summary = "Repeats every ";
                    const interval = parseInt(
                        this.editor.repeat.menu.interval.field.input[0].value
                    );
                    let frequency = "";
                    const freqValue = parseInt(
                        this.editor.repeat.menu.frequency.field.selected
                    );
                    switch (freqValue) {
                        case 4:
                            frequency = "day";
                            break;
                        case 5:
                            frequency = "week";
                            break;
                        case 6:
                            frequency = "month";
                            break;
                        case 7:
                            frequency = "year";
                    }
                    // each
                    let each = "";
                    const selected = Array.from(
                        this.editor.repeat.menu.picker.field.wrapper.querySelectorAll(
                            ".selected"
                        )
                    ).map((x) => x.getAttribute("data-value"));
                    // if selected > 1
                    if (selected.length > 1) {
                        const last = selected.pop();
                        // week : selected as weekdays
                        if (freqValue === 5)
                            each = `on ${selected
                                .map((x) => toLocalWeekDay(parseInt(x)))
                                .join(", ")} and ${toLocalWeekDay(
                                parseInt(last)
                            )}`;
                        // month : selected as dates
                        // could improve by adding 'st','rd','th' to dates depending on last digit of number, but this is useless for French language, so it'll wait.
                        else if (
                            freqValue === 6 &&
                            this.editor.repeat.menu.each.radio.checked
                        )
                            each = `on the ${selected.join(", ")} and ${last}`;
                        // year : selected as months
                        else if (freqValue === 7)
                            each = `in ${selected
                                .map((x) => toLocalMonth(parseInt(x)))
                                .join(", ")} and ${toLocalMonth(
                                parseInt(last)
                            )}`;
                    }
                    // on the
                    let onthe = "";
                    if (
                        this.editor.repeat.menu.onTheRadio.radio.checked &&
                        (freqValue === 6 || freqValue === 7)
                    )
                        onthe = `on the ${this.editor.repeat.menu.onThe.which.input[0].textContent} ${this.editor.repeat.menu.onThe.what.input[0].textContent}`;

                    summary += `${interval > 1 ? interval : ""} ${frequency}${
                        interval > 1 ? "s" : ""
                    } ${each}${each && onthe ? " " : ""}${onthe}`;
                    return (this.editor.repeat.summary.textContent = summary);

                    // if week and one day : every week.
                    // if week and more than one day : every x week on day, day and day.
                    // if month and one day : every month
                    // if month and each/onthe : every x month(s) on the 4th day/4th, 12th and 17th.
                    // if year and one month : every year
                    // if year and onthe : every x year(s) on the x day of month, month and month
                    // if year and not on the : every x year(s) in month, month and month
                },
                setFrequency: () => {
                    const frequency = parseInt(
                        this.editor.repeat.menu.frequency.field.selected
                    );
                    if (frequency !== this.focus.rrule.frequency)
                        this.editor.modified.rrule
                            ? (this.editor.modified.rrule.frequency = frequency)
                            : (this.editor.modified.rrule = {
                                  frequency: frequency,
                              });
                    else {
                        if (this.editor.modified.rrule) {
                            if (!Object.keys(this.editor.modified.rrule).length)
                                return delete this.editor.modified.rrule;
                            delete this.editor.modified.rrule.frequency;
                        }
                    }
                    this.editor.repeat.sumUpdate();
                },
                setInterval: () => {
                    const interval = parseInt(
                        this.editor.repeat.menu.interval.field.input[0].value
                    );
                    if (interval !== this.focus.rrule.interval)
                        this.editor.modified.rrule
                            ? (this.editor.modified.rrule.interval = interval)
                            : (this.editor.modified.rrule = {
                                  interval: interval,
                              });
                    else {
                        if (this.editor.modified.rrule) {
                            if (!Object.keys(this.editor.modified.rrule).length)
                                return delete this.editor.modified.rrule;
                            delete this.editor.modified.rrule.interval;
                        }
                    }
                    this.editor.repeat.sumUpdate();
                },
                reset: () => {
                    Object.keys(this.focus.rrule).length
                        ? (this.editor.modified.rrule = {})
                        : delete this.editor.modified.rrule;
                },
                setBy: () => {
                    if (this.editor.modified.rrule)
                        // reset setby
                        [
                            "by_weekday",
                            "by_date",
                            "by_month",
                            "by_setpos",
                        ].forEach((x) => delete this.editor.modified.rrule[x]);
                    const preset = parseInt(this.editor.repeat.preset.selected),
                        freq = parseInt(
                            this.editor.repeat.menu.frequency.field.selected
                        );
                    if (preset < 5 || freq === 4)
                        return console.log("setBy emptied"); // if daily, return
                    const values = Array.from(
                        // get picker values
                        this.editor.repeat.menu.picker.wrapper.querySelectorAll(
                            ".selected"
                        )
                    ).map((x) => parseInt(x.getAttribute("data-value")));
                    let pos = [], // prepare setpos values
                        by = [];
                    const setPos = () => {
                        if (
                            !this.focus.rrule.by_setpos ||
                            !arrayCompare(this.focus.rrule.by_setpos, pos).equal
                        ) {
                            this.editor.modified.rrule
                                ? (this.editor.modified.rrule.by_setpos = pos)
                                : (this.editor.modified.rrule = {
                                      by_setpos: pos,
                                  });
                        }
                        if (
                            !this.focus.rrule.by_weekday ||
                            !arrayCompare(this.focus.rrule.by_weekday, by).equal
                        ) {
                            this.editor.modified.rrule
                                ? (this.editor.modified.rrule.by_weekday = by)
                                : (this.editor.modified.rrule = {
                                      by_weekday: by,
                                  });
                        }
                    };
                    switch (
                        parseInt(this.editor.repeat.menu.onThe.which.selected)
                    ) {
                        case 0: // first
                            pos = [1];
                            break;
                        case 1: // second
                            pos = [2];
                            break;
                        case 2: //third
                            pos = [3];
                            break;
                        case 3: //fourth
                            pos = [4];
                            break;
                        case 4: // fifth
                            pos = [5];
                            break;
                        case 5: // last
                            pos = [-1];
                            break;
                    }
                    switch (
                        parseInt(this.editor.repeat.menu.onThe.what.selected)
                    ) {
                        case 0: // day
                            by = [0, 1, 2, 3, 4, 5, 6];
                            break;
                        case 1: // work day
                            by = [1, 2, 3, 4, 5];
                            break;
                        case 2: // weekend day
                            by = [0, 6];
                            break;
                        case 3: // monday
                            by = [1];
                            break;
                        case 4: // tuesday
                            by = [2];
                            break;
                        case 5: // wednesday
                            by = [3];
                            break;
                        case 6: // thursday
                            by = [4];
                            break;
                        case 7: // friday
                            by = [5];
                            break;
                        case 8: // saturday
                            by = [6];
                            break;
                        case 9: // sunday
                            by = [0];
                            break;
                    }
                    switch (freq) {
                        case 5: // week
                            if (
                                !this.focus.rrule.by_weekday ||
                                !arrayCompare(
                                    this.focus.rrule.by_weekday,
                                    values
                                ).equal
                            )
                                this.editor.modified.rrule
                                    ? (this.editor.modified.rrule.by_weekday =
                                          values)
                                    : (this.editor.modified.rrule = {
                                          by_weekday: values,
                                      });
                            break;
                        case 6: // month
                            if (
                                this.editor.repeat.menu.each.radio.checked &&
                                (!this.focus.rrule.by_date ||
                                    !arrayCompare(
                                        this.focus.rrule.by_date,
                                        values
                                    ).equal)
                            )
                                this.editor.modified.rrule
                                    ? (this.editor.modified.rrule.by_date =
                                          values)
                                    : (this.editor.modified.rrule = {
                                          by_date: values,
                                      });
                            else if (
                                this.editor.repeat.menu.onTheRadio.radio.checked
                            )
                                setPos();
                            break;
                        case 7: // year
                            if (this.focus.rrule.by_month) {
                                const compare = arrayCompare(
                                    this.focus.rrule.by_month,
                                    values
                                );
                                if (
                                    !compare.only_1.length &&
                                    !compare.only_2.length
                                )
                                    return;
                            }
                            this.editor.modified.rrule
                                ? (this.editor.modified.rrule.by_month = values)
                                : (this.editor.modified.rrule = {
                                      by_month: values,
                                  });
                            if (
                                this.editor.repeat.menu.onTheRadio.radio.checked
                            )
                                setPos();
                            break;
                    }
                    console.log(this.editor.modified.rrule);
                },
                wrapper: document.createElement("div"),
                // span summary
                // select interval (select custom opens custom repeat menu)
                preset: new Field({
                    compact: true,
                    name: "preset",
                    type: "select",
                    options: {
                        0: "none",
                        1: "every day",
                        2: "every week",
                        3: "every month",
                        4: "every year",
                        5: "custom",
                    },
                    value: 0,
                }),
                span: document.createElement("span"),

                // custom repeat menu
                menu: {
                    wrapper: document.createElement("div"),
                    // select frequency
                    frequency: {
                        field: new Field({
                            compact: true,
                            name: "frequency",
                            type: "select",
                            options: {
                                4: "daily",
                                5: "weekly",
                                6: "monthly",
                                7: "yearly",
                            },
                            value: 4,
                        }),
                        span: document.createElement("span"),
                        wrapper: document.createElement("div"),
                    },
                    // dayly :
                    // input every x days
                    interval: {
                        // use same for weeks, months, years
                        wrapper: document.createElement("div"),
                        span: document.createElement("span"),
                        field: new Field({
                            compact: true,
                            name: "interval",
                            type: "input_number",
                            min: 1,
                            max: 9999,
                            value: 1,
                        }), // type number
                        value: document.createElement("span"),
                    },
                    // weekly :
                    // input every x weeks
                    // toggle on week days
                    each: {
                        wrapper: document.createElement("div"),
                        span: document.createElement("span"),
                        radio: document.createElement("input"),
                    },
                    picker: {
                        wrapper: document.createElement("div"),
                        field: new Field({
                            compact: true,
                            name: "picker",
                            type: "picker",
                            multi: true,
                        }),
                    }, // use same picker for weekdays, dates, months
                    // monthly :
                    // input every x months
                    // toggle each date
                    // on the
                    onTheRadio: {
                        wrapper: document.createElement("div"),
                        span: document.createElement("span"),
                        radio: document.createElement("input"),
                    },
                    onThe: {
                        wrapper: document.createElement("div"),
                        span: document.createElement("span"),
                        // select first, second, third, fourth, fifth, last
                        which: new Field({
                            compact: true,
                            name: "which",
                            type: "select",
                            options: {
                                0: "first",
                                1: "second",
                                2: "third",
                                3: "fourth",
                                4: "fifth",
                                5: "last",
                            },
                            value: 0,
                        }),
                        // select day, weekend day, monday, ..., sunday
                        what: new Field({
                            compact: true,
                            name: "what",
                            type: "select",
                            options: {
                                0: "day",
                                1: "work day",
                                2: "weekend day",
                                3: "monday",
                                4: "tuesday",
                                5: "wednesday",
                                6: "thursday",
                                7: "friday",
                                8: "saturday",
                                9: "sunday",
                            },
                            value: 0,
                        }),
                    },
                    // yearly :
                    // input every x years
                    // toggle months
                    // on the
                    // select first, second, third, fourth, fifth, last
                    // select day, weekend day, monday, ..., sunday
                },
            },

            // end repeat :
            endRepeat: {
                // span summary
                summary: document.createElement("span"),
                sumUpdate: () => {
                    if (parseInt(this.editor.repeat.preset.selected) === 0) {
                        this.editor.endRepeat.summary.textContent = "";
                        return;
                    }
                    const value = parseInt(this.editor.endRepeat.type.selected);
                    let summary = "";
                    if (
                        value === 1 &&
                        parseInt(this.editor.endRepeat.count.input[0].value) > 1
                    ) {
                        // after x times
                        summary = `for ${parseInt(
                            this.editor.endRepeat.count.input[0].value
                        )} times`;
                    } else if (value === 2)
                        summary = `until ${new Date(
                            this.editor.endRepeat.until.input[0].value
                        ).toLocaleDateString(navigator.language, {
                            year: "numeric",
                            month: "long",
                            day: "numeric",
                        })}`;
                    this.editor.endRepeat.summary.textContent = summary;
                },
                setEnd: () => {
                    // reset end
                    if (this.editor.modified.rrule)
                        ["count", "until"].forEach(
                            (x) => delete this.editor.modified.rrule[x]
                        );
                    const endSelected = parseInt(
                        this.editor.endRepeat.type.selected
                    );
                    let count = 0,
                        until = null;
                    switch (endSelected) {
                        case 1: // count
                            count = parseInt(
                                this.editor.endRepeat.count.input[0].value
                            );
                            break;
                        case 2: // until
                            until = this.editor.endRepeat.until.input[0].value;
                            break;
                    }
                    if (
                        typeof this.focus.rrule.count === "undefined" ||
                        this.focus.rrule.count !== count
                    )
                        this.editor.modified.rrule
                            ? (this.editor.modified.rrule.count = count)
                            : (this.editor.modified.rrule = { count: count });
                    if (
                        (!this.focus.rrule.until && until !== null) ||
                        this.focus.rrule.until !== until
                    )
                        this.editor.modified.rrule
                            ? (this.editor.modified.rrule.until = until)
                            : (this.editor.modified.rrule = { until: until });
                },
                wrapper: document.createElement("div"),
                // select type (after, on date)
                type: new Field({
                    compact: true,
                    name: "type",
                    type: "select",
                    options: { 0: "never", 1: "after", 2: "on date" },
                    value: 0,
                }),
                span: document.createElement("span"),
                // select times (number." time(s)")
                count: new Field({
                    compact: true,
                    name: "times",
                    type: "input_number",
                    min: 2,
                    max: 9999,
                    value: 2,
                }), // type number, max 9999, min 1
                timeSpan: document.createElement("span"),
                // select date
                until: new Field({
                    compact: true,
                    min: new Date(),
                    name: "date",
                    type: "date",
                    value: new Date(),
                }),
            },

            // alerts :
            alerts: {
                span: document.createElement("span"),
                // select time, custom opens menu, none value removes line if other alert line present
                time: new Field({
                    compact: true,
                    name: "time",
                    type: "select",
                    options: {
                        0: "15 minutes before (default)",
                        1: "none",
                        2: "5 minutes before",
                        3: "10 minutes before",
                        4: "30 minutes before",
                        5: "1 hour before",
                        6: "2 hours before",
                        7: "1 day before",
                        8: "2 days before",
                        9: "custom",
                    },
                    value: 0,
                }),
                wrapper: document.createElement("div"),
                // button + to add line
                // alert custom menu :
                menu: {
                    wrapper: document.createElement("div"),
                    // select type
                    type: new Field({
                        compact: true,
                        name: "type",
                        type: "select",
                        options: { 0: "notification", 1: "Email", 2: "Sms" },
                        value: 0,
                    }),
                    // input x select time before/after, on date value changes input into datetime-local
                    times: new Field({
                        compact: true,
                        name: "times",
                        type: "input_number",
                        min: 1,
                        max: 9999,
                        value: 1,
                    }), // type number, min: 1, max: 9999
                    value: new Field({
                        compact: true,
                        name: "value",
                        type: "select",
                        options: {
                            0: "At time of event",
                            1: "minutes before",
                            2: "hours before",
                            3: "days before",
                            4: "minutes after",
                            5: "hours after",
                            6: "days after",
                            7: "on date",
                        },
                        value: 1,
                    }), // at time of event hides times input, on date hides times input shows date picker
                    date: new Field({
                        compact: true,
                        name: "date",
                        type: "datetime",
                    }),
                },
            },

            invitees: {
                // selectize invitee
                selectize: new Field({
                    compact: true,
                    name: "invitees",
                    type: "selectize",
                    task: 0,
                    multi: true,
                    placeholder: "invitees...",
                }), // select creates line with fields below
                span: document.createElement("span"),
                wrapper: document.createElement("div"),
                // span invitee's name, hover color: red, click remove
                // select role (client, ...)
                // select status
                // button send email
            },

            // add attachement with select who can access (role, user,...)

            // appointment
            appointment: {
                span: document.createElement("span"),
                // select appointment type
                type: new Field({
                    compact: true,
                    name: "appointment type",
                    type: "select",
                    options: { 0: "" },
                }), // select populated with preconfigured appointment types for calendar's client/owner
                wrapper: document.createElement("div"),
                // span datetime of next available
                next: document.createElement("span"),
            },

            // options
            options: {
                span: document.createElement("span"),
                summary: document.createElement("span"),
                wrapper: document.createElement("div"),
                // textarea||quill description
                description: new Field({
                    compact: true,
                    name: "description",
                    placeholder: "description...",
                    type: "quill",
                }),
                // checkbox show busy
                busy: {
                    wrapper: document.createElement("div"),
                    span: document.createElement("span"),
                    input: document.createElement("input"),
                },
                // checkbox transparency
                transparency: {
                    wrapper: document.createElement("div"),
                    span: document.createElement("span"),
                    input: document.createElement("input"),
                },
            },
        };

        this.editor.wrapper.className = "editor";

        // summary
        this.editor.summary.placeholder = "New event";
        this.editor.summary.addEventListener("input", (e) => {
            e.target.value !== this.focus.summary
                ? (this.editor.modified.summary = e.target.value)
                : delete this.editor.modified.summary;
            // for each element, change summary
            for (const element of Object.values(this.focus.elements))
                element.getElementsByTagName("span")[1].textContent =
                    this.editor.summary.value;
        });

        //date
        this.editor.date.wrapper.append(
            this.editor.date.summary,
            this.editor.date.allday.span,
            this.editor.date.allday.wrapper,
            this.editor.date.start.span,
            this.editor.date.start.wrapper,
            this.editor.date.end.span,
            this.editor.date.end.wrapper,
            this.editor.repeat.summary,
            this.editor.repeat.span,
            this.editor.repeat.wrapper,
            this.editor.endRepeat.summary,
            this.editor.endRepeat.span,
            this.editor.endRepeat.wrapper
        );
        // summary
        this.editor.date.summary.className = "summary";
        this.editor.date.summary.textContent = "date";
        // allday
        this.editor.date.allday.span.textContent = "All day:";
        this.editor.date.allday.input.type = "checkbox";
        this.editor.date.allday.input.setAttribute("aria-label", "allday");
        this.editor.date.allday.wrapper.append(this.editor.date.allday.input);
        this.editor.date.allday.wrapper.firstElementChild.textContent =
            "All day:";
        this.editor.date.allday.wrapper.classList.add("checkbox");
        this.editor.date.allday.input.addEventListener("change", () => {
            // would be cool to change input type for start/end to date, with value according to datetime-local start/end
            this.editor.date.allday.setAllday();
        });
        this.editor.date.start.span.textContent = "Start:";
        this.editor.date.start.wrapper.append(
            this.editor.date.start.field.wrapper
        );
        this.editor.date.start.field.input[0].addEventListener(
            "change",
            (e) => {
                this.editor.date.setStart(new Date(e.target.value));
            }
        );
        // end event :
        this.editor.date.end.span.textContent = "End:";
        this.editor.date.end.wrapper.append(this.editor.date.end.field.wrapper);
        this.editor.date.end.field.input[0].addEventListener("change", (e) => {
            this.editor.date.setEnd(new Date(e.target.value));
        });

        // repeat
        this.editor.repeat.wrapper.append(
            this.editor.repeat.preset.wrapper,
            this.editor.repeat.menu.wrapper
        );
        this.editor.repeat.summary.className = "summary";
        this.editor.repeat.span.textContent = "repeat:";
        this.editor.repeat.preset.input[0].addEventListener("select", () => {
            if (this.focus) {
                this.editor.repeat.reset();
                const value = parseInt(this.editor.repeat.preset.selected);
                this.editor.repeat.menu.wrapper.classList.remove("show");
                if (value === 0) {
                    if (Object.keys(this.focus.rrule).length)
                        this.editor.modified.rrule = {};
                    this.editor.repeat.sumUpdate();
                    this.editor.endRepeat.span.classList.add("hidden");
                    this.editor.endRepeat.wrapper.classList.add("hidden");
                } else {
                    this.editor.endRepeat.span.classList.remove("hidden");
                    this.editor.endRepeat.wrapper.classList.remove("hidden");
                    this.editor.repeat.summary.classList.remove("show");
                    this.editor.repeat.setBy();
                    switch (value) {
                        case 1:
                            this.editor.repeat.menu.frequency.field.set(4);
                            break;
                        case 2:
                            this.editor.repeat.menu.frequency.field.set(5);
                            break;
                        case 3:
                            this.editor.repeat.menu.frequency.field.set(6);
                            break;
                        case 4:
                            this.editor.repeat.menu.frequency.field.set(7);
                            break;
                    }
                    if (value === 5) {
                        // run reset each/pos
                        // run seteach & setpos (compact into one sole method, with reset in it)
                        this.editor.repeat.menu.wrapper.classList.add("show");
                    }
                    this.editor.repeat.setFrequency();
                    this.editor.repeat.setInterval();
                    this.editor.endRepeat.setEnd();
                }
            }
        });

        // repeat menu
        this.editor.repeat.menu.wrapper.append(
            this.editor.repeat.menu.frequency.wrapper,
            this.editor.repeat.menu.interval.wrapper,
            this.editor.repeat.menu.each.wrapper,
            this.editor.repeat.menu.picker.wrapper,
            this.editor.repeat.menu.onTheRadio.wrapper,
            this.editor.repeat.menu.onThe.wrapper
        );
        this.editor.repeat.menu.wrapper.className = "menu";

        // repeat menu frequency
        this.editor.repeat.menu.frequency.wrapper.append(
            this.editor.repeat.menu.frequency.span,
            this.editor.repeat.menu.frequency.field.wrapper
        );
        this.editor.repeat.menu.frequency.span.textContent = "Frequency:";
        this.editor.repeat.menu.frequency.field.input[0].addEventListener(
            "select",
            (e) => {
                const value = parseInt(e.target.getAttribute("data-value")),
                    intervalMany =
                        parseInt(
                            this.editor.repeat.menu.interval.field.input[0]
                                .value
                        ) > 1;
                this.editor.repeat.menu.wrapper.classList.remove(
                    "daily",
                    "weekly",
                    "monthly",
                    "yearly"
                );
                switch (value) {
                    case 4:
                        // daily
                        // menu.class.add = dayly
                        this.editor.repeat.menu.wrapper.classList.add("daily");
                        // interval.value = day/days depending on field value
                        this.editor.repeat.menu.interval.value.textContent =
                            intervalMany ? "days" : "day";
                        break;
                    case 5:
                        // weekly
                        // menu.class.add = weekly
                        this.editor.repeat.menu.wrapper.classList.add("weekly");
                        // interval.value =  week/weeks on:
                        this.editor.repeat.menu.interval.value.textContent =
                            intervalMany ? "weeks on:" : "week on:";
                        // picker.options = weekdays
                        this.editor.repeat.menu.picker.field.setOptions(
                            getLocalWeekDays({
                                weekstart: this.weekstart,
                                type: "narrow",
                            })
                        );
                        // set this.focus.rrule.by_weekday ?? today
                        if (this.focus.rrule.by_weekday) {
                            this.focus.rrule.by_weekday.forEach((x) =>
                                this.editor.repeat.menu.picker.field.toggleOption(
                                    x
                                )
                            );
                        } else
                            this.editor.repeat.menu.picker.field.toggleOption(
                                new Date(
                                    this.editor.date.start.field.input[0].value
                                ).getDay()
                            );
                        this.editor.repeat.menu.picker.field.enable();
                        break;
                    case 6:
                        // monthly
                        // menu.class.add = monthly
                        this.editor.repeat.menu.wrapper.classList.add(
                            "monthly"
                        );
                        // interval.value = month/months depending on field value
                        this.editor.repeat.menu.interval.value.textContent =
                            intervalMany ? "months" : "month";
                        // picker.options = monthdays
                        let monthdays = [];
                        [...Array(31).keys()]
                            .map((x) => x + 1)
                            .forEach((x) => monthdays.push([x, x]));
                        this.editor.repeat.menu.picker.field.setOptions(
                            monthdays
                        );
                        // set this.focus.rrule.by_date ?? today
                        if (this.focus.rrule.by_date) {
                            this.focus.rrule.by_date.forEach((x) =>
                                this.editor.repeat.menu.picker.field.toggleOption(
                                    x
                                )
                            );
                        } else
                            this.editor.repeat.menu.picker.field.toggleOption(
                                new Date(
                                    this.editor.date.start.field.input[0].value
                                ).getDate()
                            );
                        // onTheRadio.type = radio
                        this.editor.repeat.menu.onTheRadio.radio.type = "radio";
                        this.editor.repeat.menu.each.radio.checked = true;
                        this.editor.repeat.menu.picker.field.enable();
                        this.editor.repeat.menu.onThe.which.disable();
                        this.editor.repeat.menu.onThe.what.disable();

                        break;
                    case 7:
                        // yearly
                        // menu.class.add = yearly
                        this.editor.repeat.menu.wrapper.classList.add("yearly");
                        // interval.value = year/years in:
                        this.editor.repeat.menu.interval.value.textContent =
                            intervalMany ? "years in:" : "year in:";
                        // picker.options = months
                        this.editor.repeat.menu.picker.field.setOptions(
                            getLocalMonths("short")
                        );
                        // set this.focus.rrule.by_month ?? this month
                        if (this.focus.rrule.by_month) {
                            this.focus.rrule.by_month.forEach((month) =>
                                this.editor.repeat.menu.picker.field.toggleOption(
                                    month
                                )
                            );
                        } else
                            this.editor.repeat.menu.picker.field.toggleOption(
                                new Date(
                                    this.editor.date.start.field.input[0].value
                                ).getMonth()
                            );
                        this.editor.repeat.menu.picker.field.enable();
                        this.editor.repeat.menu.onThe.which.disable();
                        this.editor.repeat.menu.onThe.what.disable();
                        // onTheRadio.type = checkbox
                        this.editor.repeat.menu.onTheRadio.radio.type =
                            "checkbox";
                        this.editor.repeat.menu.onTheRadio.radio.checked = false;
                        break;
                }
                this.editor.repeat.setFrequency();
                this.editor.repeat.setBy();
            }
        );

        // repeat menu interval
        this.editor.repeat.menu.interval.wrapper.append(
            this.editor.repeat.menu.interval.span,
            this.editor.repeat.menu.interval.field.input[0],
            this.editor.repeat.menu.interval.value
        );
        this.editor.repeat.menu.interval.span.textContent = "Every";
        this.editor.repeat.menu.interval.value.textContent = "day";
        this.editor.repeat.menu.interval.field.input[0].addEventListener(
            "input",
            (e) => {
                let array =
                        this.editor.repeat.menu.interval.value.textContent.split(
                            " "
                        ),
                    word = array.shift();
                const lastChar = word.slice(-1);
                if (parseInt(e.target.value) > 1 && lastChar !== "s")
                    this.editor.repeat.menu.interval.value.textContent = `${word}s ${array.join(
                        " "
                    )}`;
                else if (parseInt(e.target.value) === 1 && lastChar === "s") {
                    this.editor.repeat.menu.interval.value.textContent = `${word.slice(
                        0,
                        -1
                    )} ${array.join(" ")}`;
                }
                this.editor.repeat.setInterval();
            }
        );

        // repeat menu picker
        this.editor.repeat.menu.each.wrapper.append(
            this.editor.repeat.menu.each.radio,
            this.editor.repeat.menu.each.span
        );
        this.editor.repeat.menu.each.radio.type = "radio";
        this.editor.repeat.menu.each.radio.checked = true;
        this.editor.repeat.menu.each.radio.name = "repeat-menu";
        this.editor.repeat.menu.each.radio.addEventListener("input", (e) => {
            if (
                e.target.checked &&
                this.editor.repeat.menu.picker.field.disabled
            ) {
                // if checked enable picker, disable onthe fields
                this.editor.repeat.menu.picker.field.enable();
                this.editor.repeat.menu.onThe.which.disable();
                this.editor.repeat.menu.onThe.what.disable();
            }
            this.editor.repeat.setBy();
        });
        this.editor.repeat.menu.each.span.textContent = "Each:";

        this.editor.repeat.menu.picker.wrapper.append(
            this.editor.repeat.menu.picker.field.wrapper // or input[0], to test
        );
        // this.editor.repeat.menu.picker.field.wrapper.addEventListener(
        //     "select",
        //     () => {
        //         this.editor.repeat.setBy();
        //     }
        // );

        // repeat menu onThe
        this.editor.repeat.menu.onTheRadio.wrapper.append(
            this.editor.repeat.menu.onTheRadio.radio,
            this.editor.repeat.menu.onTheRadio.span
        );
        this.editor.repeat.menu.onTheRadio.radio.type = "radio";
        this.editor.repeat.menu.onTheRadio.radio.name = "repeat-menu";
        this.editor.repeat.menu.onTheRadio.radio.addEventListener(
            "input",
            (e) => {
                if (
                    e.target.checked &&
                    !this.editor.repeat.menu.picker.field.disabled
                ) {
                    if (e.target.type === "radio")
                        this.editor.repeat.menu.picker.field.disable();
                    this.editor.repeat.menu.onThe.which.enable();
                    this.editor.repeat.menu.onThe.what.enable();
                } else if (!e.target.checked && e.target.type === "checkbox") {
                    this.editor.repeat.menu.onThe.which.disable();
                    this.editor.repeat.menu.onThe.what.disable();
                }
                this.editor.repeat.setBy();
            }
        );
        this.editor.repeat.menu.onTheRadio.span.textContent = "On the:";
        this.editor.repeat.menu.onThe.wrapper.append(
            this.editor.repeat.menu.onThe.which.wrapper,
            this.editor.repeat.menu.onThe.what.wrapper
        );
        this.editor.repeat.menu.onThe.what.input[0].addEventListener(
            "select",
            () => {
                this.editor.repeat.setBy();
            }
        );
        this.editor.repeat.menu.onThe.which.input[0].addEventListener(
            "select",
            () => {
                this.editor.repeat.setBy();
            }
        );

        // end repeat
        this.editor.endRepeat.wrapper.append(
            this.editor.endRepeat.type.wrapper,
            this.editor.endRepeat.count.input[0],
            this.editor.endRepeat.timeSpan,
            this.editor.endRepeat.until.wrapper
        );
        this.editor.endRepeat.summary.className = "summary";
        this.editor.endRepeat.span.textContent = "end repeat:";
        this.editor.endRepeat.span.classList.add("hidden");
        this.editor.endRepeat.wrapper.className = "hidden";
        this.editor.endRepeat.count.input[0].classList.add("hidden");
        this.editor.endRepeat.count.input[0].addEventListener(
            "input",
            () => {}
        );
        this.editor.endRepeat.timeSpan.textContent = "times";
        this.editor.endRepeat.timeSpan.classList.add("hidden");
        this.editor.endRepeat.until.wrapper.classList.add("hidden");
        this.editor.endRepeat.type.input[0].addEventListener("select", (e) => {
            switch (parseInt(e.target.getAttribute("data-value"))) {
                case 0:
                    // hide times & date
                    this.editor.endRepeat.count.input[0].classList.add(
                        "hidden"
                    );
                    this.editor.endRepeat.timeSpan.classList.add("hidden");
                    this.editor.endRepeat.until.wrapper.classList.add("hidden");
                    break;
                case 1:
                    // show times
                    this.editor.endRepeat.count.input[0].classList.remove(
                        "hidden"
                    );
                    this.editor.endRepeat.timeSpan.classList.remove("hidden");
                    this.editor.endRepeat.until.wrapper.classList.add("hidden");
                    break;
                case 2:
                    // show date
                    this.editor.endRepeat.count.input[0].classList.add(
                        "hidden"
                    );
                    this.editor.endRepeat.timeSpan.classList.add("hidden");
                    this.editor.endRepeat.until.wrapper.classList.remove(
                        "hidden"
                    );
                    break;
            }
            this.editor.endRepeat.setEnd();
        });
        this.editor.endRepeat.count.input[0].addEventListener("input", (e) => {
            this.editor.endRepeat.setEnd();
        });
        this.editor.endRepeat.until.input[0].addEventListener("change", () => {
            if (!this.editor.endRepeat.until.input[0].validity.valid)
                this.editor.endRepeat.until.input[0].value =
                    this.editor.endRepeat.until.input[0].min;
            this.editor.endRepeat.setEnd();
        });

        // alerts
        this.editor.alerts.wrapper.append(
            this.editor.alerts.time.wrapper,
            this.editor.alerts.menu.wrapper
        );
        // alerts menu
        this.editor.alerts.menu.wrapper.append(
            this.editor.alerts.menu.type.wrapper,
            this.editor.alerts.menu.times.input[0],
            this.editor.alerts.menu.value.wrapper,
            this.editor.alerts.menu.date.wrapper
        );
        this.editor.alerts.menu.type.wrapper.className = "menu";

        //invitees
        this.editor.invitees.wrapper.append(
            this.editor.invitees.selectize.wrapper
        );

        // appointment
        this.editor.appointment.wrapper.append(
            this.editor.appointment.type.wrapper,
            this.editor.appointment.next
        );

        // options
        this.editor.options.wrapper.append(
            this.editor.options.summary,
            this.editor.options.description.wrapper,
            this.editor.options.busy.wrapper,
            this.editor.options.transparency.wrapper
        );
        this.editor.options.summary.className = "summary";
        this.editor.options.summary.textContent = "options:";

        // busy
        this.editor.options.busy.input.type = "checkbox";
        this.editor.options.busy.wrapper.append(
            document.createElement("span"),
            this.editor.options.busy.input
        );
        this.editor.options.busy.wrapper.firstElementChild.textContent =
            "Busy:";
        this.editor.options.busy.input.addEventListener("change", (e) => {
            console.log(e.target.checked);
        });
        this.editor.options.busy.wrapper.classList.add("checkbox");

        // transparency
        this.editor.options.transparency.input.type = "checkbox";
        this.editor.options.transparency.wrapper.append(
            document.createElement("span"),
            this.editor.options.transparency.input
        );
        this.editor.options.transparency.wrapper.firstElementChild.textContent =
            "Transparency:";
        this.editor.options.transparency.wrapper.classList.add("checkbox");

        this.editor.options.transparency.input.addEventListener(
            "change",
            (e) => {
                console.log(e.target.checked);
            }
        );

        this.editor.wrapper.append(
            this.editor.summary,
            document.createElement("hr"),
            this.editor.date.wrapper,
            document.createElement("hr"),
            this.editor.alerts.wrapper,
            document.createElement("hr"),
            this.editor.invitees.wrapper,
            document.createElement("hr"),
            this.editor.appointment.wrapper,
            document.createElement("hr"),
            this.editor.options.wrapper
        );

        // minicalendar menu
        let todayButton = document.createElement("button"),
            calAdd = document.createElement("button");
        this.minical.selector = document.createElement("ul"); // menu with toggle to show cal, color selector, and remove/suppr if owner button.
        this.minical.selector.textContent = "🗓";
        todayButton.textContent = "🩴";
        todayButton.addEventListener("click", () =>
            this.minicalFocus(new Date())
        );
        calAdd.textContent = "🧉";
        calAdd.addEventListener("click", () => this.newCalendar());
        this.menu.append(todayButton, this.minical.selector, calAdd);
        this.getCalendars();
        this.generateCalendar();
        this.wrapper.append(
            this.toggle,
            this.menu,
            this.editor.wrapper,
            this.minical.cal,
            this.bigcal.wrapper
        );
    }
    addCalendar(calendar) {
        // add calendar to this.calendars
        this.calendars[calendar.id] = {
            name: calendar.name,
            description: calendar.description,
            color: calendar.color ?? undefined,
            owner: calendar.owner,
            read_only: calendar.read_only,
            components: {},
            visible: calendar.visible,
        };
        this.minicalAddCalendar(`${calendar.id}`);
    }
    /**
     * Adds event to calendar from object data.
     */
    addComponent(idcal, component) {
        if (!this.calendars[idcal].components)
            this.calendars[idcal].components = {};
        // if component exists, remove all elements  before updating component
        if (this.calendars[idcal].components[component.uid])
            Object.values(
                this.calendars[idcal].components[component.uid].elements
            ).forEach((x) => x.remove());
        this.calendars[idcal].components[component.uid] = {
            id: component.idcal_component,
            modified: component.modified,
            start: new Date(`${component.start.replace(" ", "T")}Z`),
            end: new Date(`${component.end.replace(" ", "T")}Z`),
            allday: component.allday ?? 0,
            elements: {},
            rrule: component.rrule ?? {},
            summary: component.summary,
            type: component.type,
            transparency: component.transparency,
            sequence: component.sequence,
        };
        if (component.rrule) {
            // if (component.rrule.until) {
            //     component.rrule.until = toHTMLInputDateValue(
            //         new Date(component.rrule.until)
            //     );
            // }
            if (component.rrule.by_weekday)
                component.rrule.by_weekday = component.rrule.by_weekday
                    .split(",")
                    .map((x) => parseInt(x));
            // component.rrule.by_weekday.split(",");
            if (component.rrule.by_date)
                component.rrule.by_date = component.rrule.by_date
                    .split(",")
                    .map((x) => parseInt(x));
            // component.rrule.by_date = component.rrule.by_date.split(",");
            if (component.rrule.by_month)
                component.rrule.by_month = component.rrule.by_month
                    .split(",")
                    .map((x) => parseInt(x));
            // component.rrule.by_month = component.rrule.by_month.split(",");
            if (component.rrule.by_setpos)
                component.rrule.by_setpos = component.rrule.by_setpos
                    .split(",")
                    .map((x) => parseInt(x));
            // component.rrule.by_setpos.split(",");
            this.calendars[idcal].components[component.uid].rrule =
                component.rrule;
        }
        if (component.rdates)
            this.calendars[idcal].components[component.uid].rdates =
                component.rdates.split(",");
        if (component.rrule || component.rdates) {
            if (component.exceptions.length)
                this.calendars[idcal].components[component.uid].exceptions =
                    component.exceptions;
            if (component.recur_id)
                this.calendars[idcal].components[component.uid].r_id =
                    component.recur_id;
            this.calendars[idcal].components[component.uid].thisandfuture =
                component.thisandfuture ?? 0;
        }
        if (component.alarms)
            this.calendars[idcal].components[component.uid].alarms =
                component.alarms;
        this.placeComponent(idcal, component.uid);
    }
    /**
     * Add month to calendars.
     * @param {Date} date
     * @return {HTMLElement} minical month
     */
    addMonth(date) {
        // month = first week to last week, including other monthes days
        // => this, but with the option to hide duplicate weeks to show a compact view.
        const year = date.getFullYear(),
            month = date.getMonth(),
            now = new Date(),
            start = toMYSQLDTString(new Date(year, month)),
            end = toMYSQLDTString(new Date(year, month + 1));
        socket.send({
            f: 22,
            s: start,
            e: end,
        });
        for (let cal of [this.minical, this.bigcal]) {
            // if year not in calendar, create it with its months.
            if (!cal.years[year]) {
                let yearWrapper = document.createElement("div");
                yearWrapper.setAttribute("data-year", year);
                cal.years[year] = { months: {}, wrapper: yearWrapper };
                for (let i = 0; i < 12; i++) {
                    const monthDate = new Date(year, i);
                    let monthWrapper = document.createElement("div");
                    setElementAttributes(monthWrapper, {
                        "data-month":
                            monthDate.toLocaleString("default", {
                                month: "long",
                            }) + ` ${year}`,
                        "data-value": monthDate.valueOf(),
                    });
                    monthWrapper.className = "hidden";
                    cal.years[year].months[i] = monthWrapper;
                    if (cal === this.minical) {
                        monthWrapper.addEventListener("click", () => {
                            this.bigcalFocus(monthDate, "month", true);
                        });
                    }
                    yearWrapper.append(monthWrapper);
                }
                // set a way to insert year in right place.
                cal.cal.children.length &&
                year < cal.cal.firstElementChild.getAttribute("data-year")
                    ? cal.cal.prepend(yearWrapper)
                    : cal.cal.append(yearWrapper);
            }
            // fill month
            let monthWrapper = cal.years[year].months[month],
                day = getFirstDayOfWeek(date),
                weekWrapper;
            if (!monthWrapper.innerHTML) {
                monthWrapper.classList.remove("hidden");
                while (day <= getLastDayOfWeek(new Date(year, month + 1, 0))) {
                    const dayDate = new Date(
                        day.getFullYear(),
                        day.getMonth(),
                        day.getDate()
                    );
                    // if first day of week, create week
                    if (day.getDay() === this.weekstart) {
                        const weekNumber = getWeekNumber(day);
                        weekWrapper = document.createElement("div");
                        weekWrapper.setAttribute("data-week", weekNumber);
                        if (cal === this.minical) {
                            weekWrapper.addEventListener("click", (e) => {
                                e.stopPropagation();
                                this.bigcalFocus(dayDate, "week", true);
                            });
                        }
                        monthWrapper.append(weekWrapper);
                    }
                    let dayWrapper = document.createElement("div"),
                        info = document.createElement("div"),
                        allday = document.createElement("div"),
                        regular = document.createElement("div");
                    info.append(document.createElement("span"));
                    info.firstElementChild.textContent =
                        new Intl.DateTimeFormat("fr", {
                            weekday: "short",
                            day: "numeric",
                        }).format(day);
                    dayWrapper.append(info, allday, regular);
                    dayWrapper.setAttribute("data-date", day.getDate());
                    if (dayDate.getMonth() !== date.getMonth())
                        dayWrapper.classList.add("fade");
                    if (dayDate.toDateString() === now.toDateString())
                        dayWrapper.classList.add("today");
                    if (cal === this.minical) {
                        dayWrapper.addEventListener("click", (e) => {
                            e.stopPropagation();
                            this.bigcalFocus(dayDate, "day", true);
                        });
                    }
                    weekWrapper.append(dayWrapper);
                    day.setDate(day.getDate() + 1);
                }
            } else console.info(`Month ${month}-${year} already created.`);
        }

        // request month events if not in object.

        return this.minical.years[year].months[month];
    }
    /**
     * Focus bigcal on date and show it.
     * @param {Date} date
     * @param {String} type Values: "year","month","week","day".
     * @param {Boolean} lock
     */
    bigcalFocus(date, type, lock = false) {
        // if bigcal locked && same date, remove lock.
        if (
            this.bigcal.lock &&
            this.bigcal.wrapper.classList.contains(type) &&
            this.bigcal.focus?.date === date
        )
            this.bigcalLock();
        else {
            this.bigcal.focus = { date: date, type: type };
            this.bigcalLock(lock);
        }
        // set type by applying class to bigcal
        for (const t of ["year", "month", "week", "day"])
            type === t
                ? this.bigcal.wrapper.classList.add(t)
                : this.bigcal.wrapper.classList.remove(t);
        // focus to date
        let target, x, y;
        switch (type) {
            case "year":
                target = this.bigcal.years[date.getFullYear()].wrapper;
                break;
            case "month":
                target =
                    this.bigcal.years[date.getFullYear()].months[
                        date.getMonth()
                    ];
                x =
                    target.offsetLeft +
                    target.offsetParent.offsetLeft +
                    target.offsetParent.offsetParent.offsetLeft;
                y =
                    target.offsetTop +
                    target.offsetParent.offsetTop +
                    target.offsetParent.offsetParent.offsetTop;
                break;
            case "week":
            case "day":
                const infoWidth = convertRemToPixels(
                    parseFloat(
                        window
                            .getComputedStyle(this.bigcal.wrapper)
                            .getPropertyValue("--info-width")
                    )
                );
                target = this.bigcal.years[date.getFullYear()].months[
                    date.getMonth()
                ].querySelector(
                    `[data-week="${getWeekNumber(
                        date
                    )}"] [data-date="${date.getDate()}"]`
                );
                x =
                    target.offsetLeft +
                    target.offsetParent.offsetLeft +
                    target.offsetParent.offsetParent.offsetLeft -
                    infoWidth;
                break;
        }
        this.bigcal.cal.scrollTo({ top: y, left: x, behavior: "auto" });
    }
    /**
     * Applies/removes lock property and class to bigcal.
     * @param {Boolean} [lock] Default: false.
     */
    bigcalLock(lock = false) {
        this.bigcal.lock = lock;
        lock
            ? this.bigcal.wrapper.classList.add("locked")
            : this.bigcal.wrapper.classList.remove("locked");
    }
    /**
     * @param {Date} start
     * @param {Date} end
     */
    calcEventHeight(start, end) {
        return `${
            ((end.valueOf() - start.valueOf()) / 1000 / 60 / 60) *
            this.bigcal.layout.firstElementChild.firstElementChild.offsetHeight
        }px`;
    }
    /**
     * @param {Date} date
     */
    calcEventTop(date) {
        return `${
            this.bigcal.layout.firstElementChild.firstElementChild
                .offsetHeight *
            (date.getHours() + date.getMinutes() / 60)
        }px`;
    }
    /**
     * Removes events and cal from app.
     * @param {Number} idcal
     */
    calRemove(idcal) {
        // remove cal components
        if (this.calendars[idcal].components)
            for (const component of Object.values(
                this.calendars[idcal].components
            ))
                for (const element of Object.values(component.elements))
                    element.remove();
        // remove cal from minical
        this.calendars[idcal].li.remove();
        // remove cal from bopcal
        delete this.calendars[idcal];
        // set active on next calendar, else undefined
        this.active =
            this.active === idcal && this.calendars.length
                ? Object.keys(this.calendars)[0]
                : undefined;
    }
    calUnsubscribe(idcal) {
        // if cal owner, alert removal = deleting events no return
        if (this.calendars[idcal].owner) {
            msg.new({
                type: "danger",
                content:
                    "Supprimer définitivement ce calendrier et tous ses évènements ?",
                btn1text: "confirmer",
                btn1listener: () => {
                    console.log("Big Badaboum !");
                    // send kill message to server
                    socket.send({ f: 29, c: idcal });
                    // close
                    msg.close();
                },
            });
        } else {
            msg.new({
                type: "warning",
                content: "Vous désabonner de ce calendrier ?",
                btn1text: "confirmer",
                btn1listener: () => {
                    // remove cal from user_has_cal
                    socket.send({ f: 30, c: idcal });
                    // remove calendar and events from app
                    this.calRemove(idcal);
                    // close
                    msg.close();
                },
            });
        }
    }
    calUpdateColor(idcal, color) {
        // update events color in app.
        this.calendars[idcal].color = color;
        if (this.calendars[idcal].components)
            for (const component of Object.values(
                this.calendars[idcal].components
            )) {
                for (const element of Object.values(component.elements))
                    element.style.setProperty("--event-color", color);
            }
        // update calendar color in db
        socket.send({
            f: 28,
            c: idcal,
            x: color,
        });
    }
    clickEvents(e) {
        // if target is in bigcal calendar
        if (this.bigcal.cal.contains(e.target)) {
            // if target is an event
            if (e.target.closest("[data-uid]")) {
                const componentElement = e.target.closest("[data-uid]"),
                    idcal = componentElement.getAttribute("data-cal"),
                    uid = componentElement.getAttribute("data-uid"),
                    component = this.calendars[idcal].components[uid];
                // move editor towards componentElement and fill with component's data;
                if (this.editor.uid !== uid)
                    this.editorFocus(idcal, uid, componentElement);
                // if target has focus, open editor
                if (component === this.focus) this.editorShow();
                return;
            }
            // if target is a day
            if (e.target.closest("[data-date")) {
                const dayDate = new Date(
                    parseInt(
                        e.target
                            .closest("[data-month]")
                            .getAttribute("data-value")
                    )
                );
                dayDate.setDate(
                    parseInt(
                        e.target
                            .closest("[data-date]")
                            .getAttribute("data-date")
                    )
                );
                // if target is info
                if (
                    e.target
                        .closest("[data-date]")
                        .getElementsByTagName("div")[0]
                        .contains(e.target)
                )
                    // bigcal focus day
                    this.bigcalFocus(dayDate, "day", true);
            }

            // if number (day, week, month), set view to corresponding date.
            // if event, set focus to it
        }
        // if target is in bigcal week info
        if (
            this.bigcal.info.contains(e.target) &&
            this.bigcal.info.firstElementChild.textContent !== "W##"
        ) {
            const weekElement = this.bigcal.cal.querySelector(
                `[data-week="${this.bigcal.info.firstElementChild.textContent.slice(
                    1
                )}"`
            );
            let weekDate = new Date(
                parseInt(
                    weekElement
                        .closest("[data-month]")
                        .getAttribute("data-value")
                )
            );
            weekDate.setDate(
                weekElement
                    .querySelector("[data-date]:not(.fade)")
                    .getAttribute("data-date")
            );
            weekDate = getFirstDayOfWeek(weekDate);
            this.bigcalFocus(weekDate, "week", true);
        }
        if (this.editor.wrapper.contains(e.target)) {
            if (this.editor.date.wrapper.contains(e.target))
                this.editor.date.wrapper.classList.add("expanded");
            else {
                this.editor.date.wrapper.classList.remove("expanded");
                this.editor.endRepeat.sumUpdate();
            }
            this.editor.repeat.wrapper.contains(e.target)
                ? this.editor.repeat.wrapper.classList.add("expanded")
                : this.editor.repeat.wrapper.classList.remove("expanded");
            this.editor.endRepeat.wrapper.contains(e.target)
                ? this.editor.endRepeat.wrapper.classList.add("expanded")
                : this.editor.endRepeat.wrapper.classList.remove("expanded");
            this.editor.alerts.wrapper.contains(e.target)
                ? this.editor.alerts.wrapper.classList.add("expanded")
                : this.editor.alerts.wrapper.classList.remove("expanded");
            this.editor.invitees.wrapper.contains(e.target)
                ? this.editor.invitees.wrapper.classList.add("expanded")
                : this.editor.invitees.wrapper.classList.remove("expanded");
            this.editor.appointment.wrapper.contains(e.target)
                ? this.editor.appointment.wrapper.classList.add("expanded")
                : this.editor.appointment.wrapper.classList.remove("expanded");
            this.editor.options.wrapper.contains(e.target)
                ? this.editor.options.wrapper.classList.add("expanded")
                : this.editor.options.wrapper.classList.remove("expanded");
            if (
                this.editor.repeat.menu.wrapper.classList.contains("show") &&
                !this.editor.repeat.menu.wrapper.contains(e.target) &&
                !this.editor.repeat.preset.wrapper.contains(e.target)
            ) {
                this.editor.repeat.menu.wrapper.classList.remove("show");
                this.editor.repeat.sumUpdate();
            }
            return;
        } else if (this.editor.wrapper.classList.contains("show"))
            this.editorHide();
        if (this.focus)
            Object.values(this.focus.elements).forEach((x) =>
                x.classList.remove("focus")
            );
        delete this.focus;
    }
    /**
     * Applys new range to component in db.
     * @param {Number} idcal
     * @param {String} uid
     */
    componentApplyRange(idcal, uid) {
        const component = this.calendars[idcal].components[uid];
        socket.send({
            c: idcal,
            e: toMYSQLDTString(component.end),
            f: 32,
            i: component.id,
            m: component.modified,
            s: toMYSQLDTString(component.start),
            u: uid,
        });
    }
    // componentFocus(el) {
    //     if (this.focus && this.focus === el) {
    //         delete this.focus;
    //         el.classList.remove("focus");
    //         return;
    //     } else {
    //         if (this.focus) this.focus.classList.remove("focus");
    //         el.classList.add("focus");
    //         this.focus = el;
    //     }
    // }
    componentRemove(idcal, uid) {
        socket.send({
            c: idcal,
            f: 33,
            i: this.calendars[idcal].components[uid].id,
            u: uid,
        });
    }
    componentUpdate(data) {
        console.log("got updated component");
        // const component = cal.calendars[data.c].components[data.u];
        let component = data.e;
        component.modified = data.m;
        this.addComponent(data.c, data.e);
        // apply component update
        // place component

        // for (let element of Object.values(component.elements)) element.classList.remove('applying');
    }
    destroy() {
        this.minical.observer?.disconnect();
        this.wrapper.innerHTML = "";
        this.wrapper.className = "loading hidden";
        BopCal.bopcals.splice(this.id, 1);
    }
    static destroyAll() {
        for (let cal of BopCal.bopcals) {
            cal.controller.abort();
            cal.destroy();
        }
    }
    editorApply(options) {
        console.log("editor apply");
        console.log(options);
        const component = this.calendars[options.idcal].components[options.uid];
        // apply loading to component's elements
        for (const element of Object.values(component.elements))
            element.classList.add("applying");
        // send modifications to db
        socket.send({
            c: options.idcal,
            e: options.modified,
            f: 34,
            i: component.id,
            m: component.modified,
            u: options.uid,
        });
    }
    editorFocus(idcal, uid, element) {
        if (
            this.editor.modified?.rrule &&
            !Object.keys(this.editor.modified.rrule).length &&
            !Object.keys(this.focus.rrule).length
        )
            delete this.editor.modified.rrule;
        if (Object.keys(this.editor.modified).length)
            this.editorApply({
                idcal: this.editor.idcal,
                uid: this.editor.uid,
                modified: this.editor.modified,
            });
        this.editor.modified = {};
        this.editorReset();
        this.editor.idcal = idcal;
        this.editor.uid = uid;
        const component = this.calendars[idcal].components[uid],
            targetX =
                element.getBoundingClientRect().x -
                this.editor.wrapper.offsetParent.offsetLeft -
                this.editor.wrapper.offsetWidth -
                convertRemToPixels(1),
            targetY =
                element.getBoundingClientRect().y +
                element.offsetHeight / 2 -
                this.editor.wrapper.offsetParent.offsetTop -
                this.editor.wrapper.offsetHeight / 2,
            lowLimitX = -this.wrapper.offsetLeft,
            highLimitX =
                document.body.offsetWidth -
                this.wrapper.offsetLeft -
                this.editor.wrapper.offsetWidth,
            lowLimitY = -this.wrapper.offsetTop,
            highLimitY =
                document.body.offsetHeight -
                this.wrapper.offsetTop -
                this.editor.wrapper.offsetHeight;
        // move editor towards component element
        if (targetX >= lowLimitX && targetX <= highLimitX)
            this.editor.wrapper.style.left = `${targetX}px`;
        if (targetX < lowLimitX)
            this.editor.wrapper.style.left = `${lowLimitX}px`;
        if (targetX > highLimitX)
            this.editor.wrapper.style.left = `${highLimitX}px`;
        if (targetY >= lowLimitY && targetY <= highLimitY)
            this.editor.wrapper.style.top = `${targetY}px`;
        if (targetY < lowLimitY)
            this.editor.wrapper.style.top = `${lowLimitY}px`;
        if (targetY > highLimitY)
            this.editor.wrapper.style.top = `${highLimitY}px`;

        // populate editor with component's data
        this.editor.summary.value = component.summary;
        this.editor.date.allday = component.all_day;
        this.editor.date.setStart(component.start);
        this.editor.date.setEnd(component.end);

        // if repeat, fill repeat fields/summary & show endrepeat
        if (Object.keys(component.rrule).length) {
            // preset
            let preset = 0; // none
            if (
                component.rrule.interval === 1 &&
                component.rrule.frequency === 4
            ) {
                preset = 1; // if freq=4 && inter=1 => daily
            } else if (
                component.rrule.interval === 1 &&
                component.rrule.frequency === 5 &&
                !component.rrule.by_weekday
            ) {
                preset = 2; // if freq=5 && inter=1 && !by_weekday => weekly
            } else if (
                component.rrule.interval === 1 &&
                component.rrule.frequency === 6 &&
                !component.rrule.by_date &&
                !component.rrule.by_setpos
            ) {
                preset = 3; // if freq=6 && inter=1 && !by_date && !by_setpos => monthly
            } else if (
                component.rrule.interval === 1 &&
                component.rrule.frequency === 7 &&
                !component.rrule.by_month
            ) {
                preset = 4; // if freq=7 && inter=1 && !by_month => yearly
            } else preset = 5; // else custom
            this.editor.repeat.preset.set(preset);
            // frequency
            console.log(component.rrule.frequency);
            console.info(this.editor.repeat.menu.frequency.field.options);
            this.editor.repeat.menu.frequency.field.set(
                component.rrule.frequency
            );
            // interval
            this.editor.repeat.menu.interval.field.input[0].value =
                component.rrule.interval;
            // by_weekday
            if (component.rrule.by_weekday) {
                if (component.rrule.frequency === 5)
                    component.rrule.by_weekday.forEach((wd) =>
                        this.editor.repeat.menu.picker.field.toggleOption(
                            parseInt(wd)
                        )
                    );
                else {
                    let whatOption;
                    switch (component.rrule.by_weekday.length) {
                        case 7: // weekdays
                            whatOption = 0;
                            break;
                        case 5: // workdays
                            whatOption = 1;
                            break;
                        case 2: // weekend days
                            whatOption = 2;
                            break;
                        default: // weekday
                            const whatOptions = {
                                1: 3,
                                2: 4,
                                3: 5,
                                4: 6,
                                5: 7,
                                6: 8,
                                0: 9,
                            };
                            whatOption =
                                whatOptions[
                                    parseInt(component.rrule.by_weekday[0])
                                ];
                    }
                    this.editor.repeat.menu.onThe.what.set(whatOption);
                }
            }
            // by_date
            if (component.rrule.by_date) {
                component.rrule.by_date.forEach((date) =>
                    this.editor.repeat.menu.picker.field.toggleOption(
                        parseInt(date)
                    )
                );
            }
            // by_month
            if (component.rrule.by_month) {
                component.rrule.by_month.forEach((month) =>
                    this.editor.repeat.menu.picker.field.toggleOption(
                        parseInt(month)
                    )
                );
            }
            // by_setpos
            if (component.rrule.by_setpos) {
                // if ([6, 7].includes(component.rrule.frequency))
                // if month, ontheradio
                this.editor.repeat.menu.onTheRadio.radio.checked = true;
                // set which
                const whichOptions = {
                    1: 0, // first day
                    2: 1, // second day
                    3: 2, // third day
                    4: 3, // fourth day
                    5: 4, // fifth day
                    "-1": 5, // last day
                };
                this.editor.repeat.menu.onThe.which.set(
                    whichOptions[component.rrule.by_setpos[0]]
                );
            }
            // if count
            if (component.rrule.count) {
                this.editor.endRepeat.count.input[0].value =
                    component.rrule.count;
                this.editor.endRepeat.type.set(1);
            }
            // if until
            if (component.rrule.until) {
                this.editor.endRepeat.until.input[0].value =
                    component.rrule.until.slice(0, 10);
                this.editor.endRepeat.type.set(2);
            }
        }

        // if invitees
        // if appointment type
        // if description
        // if busy/transparency

        this.editor.repeat.sumUpdate();
        this.editor.endRepeat.sumUpdate();
        this.editor.wrapper.style.setProperty(
            "--editor-color",
            element.style.getPropertyValue("--event-color")
        );
    }
    editorHide() {
        this.editor.wrapper.classList.remove("show", "date-edition");
        this.editor.wrapper
            .querySelector(".expanded")
            ?.classList.remove("expanded");
        // if modifications
        if (
            this.editor.modified?.rrule &&
            !Object.keys(this.editor.modified.rrule).length &&
            !Object.keys(this.focus.rrule).length
        )
            delete this.editor.modified.rrule;
        if (Object.keys(this.editor.modified).length) {
            this.editorApply({
                idcal: this.editor.idcal,
                uid: this.editor.uid,
                modified: this.editor.modified,
            });
        }
        this.editor.modified = {};
        delete this.editor.idcal;
        delete this.editor.uid;
    }
    editorReset() {
        // summary
        // repeat
        // preset: none
        this.editor.repeat.preset.set(0);
        // frequency: 4
        // this.editor.repeat.menu.frequency.field.set(4);
        // // interval: 1
        // this.editor.repeat.menu.interval.field.input[0].value = 1;
        // // each radio checked: true
        // this.editor.repeat.menu.each.radio.checked = true;
        // // picker: clear selected
        // this.editor.repeat.menu.picker.clear();
        // // ontheradio checked: false
        // this.editor.repeat.menu.onTheRadio.radio.checked = false;
        // // onthe which: 0
        // this.editor.repeat.menu.onThe.which.set(0);
        // // onthe what: 0
        // this.editor.repeat.menu.onThe.what.set(0);

        // // endRepeat
        // // type: 0
        this.editor.endRepeat.type.set(0);
        // // count: 0
        // this.editor.endRepeat.count.input[0].value = 0;
        // // until: new Date()
        // this.editor.endRepeat.until.input[0].value = toHTMLInputDateValue(
        //     new Date()
        // );
    }
    editorShow() {
        this.editor.wrapper
            .querySelector(".expanded")
            ?.classList.remove("expanded");
        this.editor.wrapper.classList.add("show");
        this.editor.summary.focus();
    }
    /**
     * First load of calendar.
     * @param {Date} [date]
     */
    generateCalendar(date = new Date()) {
        // set base date of calendar
        this.baseDate = date;

        for (const month of [
            new Date(this.baseDate.getFullYear(), this.baseDate.getMonth() - 1),
            new Date(this.baseDate.getFullYear(), this.baseDate.getMonth()),
            new Date(this.baseDate.getFullYear(), this.baseDate.getMonth() + 1),
        ])
            this.addMonth(month);

        // set up intersectionObservers on mini and big cal to load months with scroll.

        // note for self: intersectionObserver works whatever way an element enters its root, vertical or horizontal scroll, or anything else.
        const observerOptions = {
                root: this.wrapper,
                rootMargin: "0px 0px",
                threshold: 1,
            },
            quantity = 1, // how many months to load at once
            loadloop = (date, up = false) => {
                let last;
                const monthHeight = convertRemToPixels(14);
                for (let i = 1; i <= quantity; i++) {
                    const scroll = this.minical.cal.scrollTop;
                    date.setMonth(
                        up ? date.getMonth() - 1 : date.getMonth() + 1
                    );
                    if (up) {
                        this.minical.cal.scrollTop = scroll + monthHeight;
                    }
                    last = this.addMonth(date);
                }
                return last;
            },
            action = () => {},
            calIntersect = (entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.target === this.minical.bottomMonth) {
                        if (entry.isIntersecting) {
                            console.log(`load ${quantity} months at bottom`);
                            // get date of month
                            const date = new Date(
                                parseInt(
                                    this.minical.bottomMonth.getAttribute(
                                        "data-value"
                                    )
                                )
                            );
                            this.minical.observer.unobserve(
                                this.minical.bottomMonth
                            );
                            this.minical.bottomMonth = loadloop(date);
                            this.minical.observer.observe(
                                this.minical.bottomMonth
                            );
                            // check firstMonth, for each month too far up from top, unload month from dom
                        }
                        // add month at end, set it to be lastMonth
                        // check if first month out of range of intersection observer, then empty it and observe next month
                    } else if (entry.target === this.minical.topMonth) {
                        if (entry.isIntersecting) {
                            console.log(`load ${quantity} months at top`);
                            // get date of month
                            const date = new Date(
                                parseInt(
                                    this.minical.topMonth.getAttribute(
                                        "data-value"
                                    )
                                )
                            );
                            this.minical.observer.unobserve(
                                this.minical.topMonth
                            );
                            delete this.minical.topMonth;
                            this.minical.topMonth = loadloop(date, true);
                            this.minical.observer.observe(
                                this.minical.topMonth
                            );
                            // check lastMonth, for each month too far down from bottom, unload month from dom
                        }
                        // add month at begining, set it to be firstMonth
                        // check if last month out of range of intersection observer, then empty it and observe previous month
                    }
                });
            };
        let months = this.minical.cal.querySelectorAll(
            "[data-month]:not(.hidden)"
        );
        this.minical.bottomMonth = months[months.length - 1];
        this.minical.topMonth = months[0];
        this.minical.observer = new IntersectionObserver(
            calIntersect,
            observerOptions
        );
        this.minical.observer.observe(this.minical.topMonth);
        this.minical.observer.observe(this.minical.bottomMonth);

        // this.minicalFocus(new Date());
        // month name:
        // position: absolute
        // top = top from row containing 1st of month - 1 x row height
        // left: 100 %
        // width = (count(row from 1st of month until 1st of next month) - 1) x row height
        // rotate 90, origin: bottom left
        // same for week number
        // same for year ?
    }
    getCalendars() {
        socket.send({
            f: 21,
        });
    }
    /**
     *
     * @param {PointerEvent} e
     * @param {Number} [offset=0]
     */
    getCursorDate(e, offset = 0) {
        // if e.target = allday ...
        // else
        const monthEl = e.target.closest("[data-month]"),
            day = e.target.closest("[data-date]"),
            regular = day.lastElementChild,
            // const monthEl = e.target.parentNode.parentNode,
            //     day = e.target.getAttribute("data-date"),
            hours =
                (e.clientY - offset - regular.getBoundingClientRect().y) /
                (regular.offsetHeight / 24);
        let date = new Date(parseInt(monthEl.getAttribute("data-value")));
        // if element classlist contains fade. day belongs to previous/next month
        if (day.classList.contains("fade"))
            day.parentNode === monthEl.firstElementChild
                ? date.setMonth(date.getMonth() - 1)
                : date.setMonth(date.getMonth() + 1);
        date.setDate(day.getAttribute("data-date"));
        date.setTime(date.getTime() + hours * 60 * 60 * 1000);
        return date;
    }
    static getComponents(idcal, start, end) {
        // check if asked range in stored range, else fetch.
        // foreach day of range, check if data in cache, else fetch.
        socket.send({
            f: 22,
            start: start,
            end: end,
            c: idcal,
        });
    }
    minicalAddCalendar(idcal) {
        let calendar = this.calendars[idcal],
            wrapper = document.createElement("li"),
            check = new Field({
                type: "checkbox",
                name: "visible",
                compact: true,
            }),
            name = document.createElement("span"),
            colorSelect = document.createElement("input"),
            removeButton = document.createElement("button");
        name.textContent = calendar.name;
        name.addEventListener("click", () => {
            this.setActiveCal(idcal);
        });

        // check.type = "checkbox";
        check.input[0].checked = calendar.visible;
        // if checked, show events, else hide them
        check.input[0].addEventListener("change", (e) => {
            this.toggleCalVisibility(idcal, e.target.checked);
            e.target.blur();
        });
        colorSelect.type = "color";
        if (!calendar.color) calendar.color = "#759ece";
        colorSelect.value = calendar.color;
        colorSelect.addEventListener("input", (e) => {
            const color = e.target.value;
            if (this.calendars[idcal].color !== color) {
                this.calUpdateColor(idcal, color);
            }
        });
        removeButton.textContent = "❌";
        removeButton.addEventListener("click", () => {
            // alert to confirm:
            console.log(`remove cal #${idcal}`);
            // if calendar owner, delete calendar
            this.calUnsubscribe(idcal);
            // else remove
        });
        wrapper.append(check.wrapper, name, colorSelect, removeButton);
        this.calendars[idcal].li = wrapper;
        this.minical.selector.append(wrapper);
        if (!this.active) this.setActiveCal(idcal);
    }
    /**
     * Focus actual date in minical.
     * @param {Date} date
     */
    minicalFocus(date) {
        const month =
                this.minical.years[date.getFullYear()].months[date.getMonth()],
            x = month.offsetLeft,
            y = month.offsetTop,
            day = month.querySelector(
                `[data-date="${date.getDate()}"]:not(.fade)`
            );
        this.minical.cal.scrollTo({ top: y, left: x, behavior: "smooth" });
        this.minical.cursor.style.top = `${
            day.offsetParent.offsetTop + month.offsetTop
        }px`;
        this.minical.cursor.style.left = `${
            day.offsetLeft +
            day.offsetParent.offsetLeft +
            month.offsetLeft +
            day.closest("[data-year]").offsetLeft
        }px`;
        if (this.minical.cal.querySelector("[data-week].active"))
            this.minical.cal
                .querySelector("[data-week].active")
                .classList.remove("active");
        day.closest("[data-week]").classList.add("active");
    }
    newCalendar() {
        if (Modal.modals.filter((x) => x.task === 27).length) {
            return Modal.modals.filter((x) => x.task === 27)[0].close();
        }
        const modal = new Modal({
            buttons: [
                {
                    listener: () => {
                        modal.close();
                    },
                },
                { text: "créer", requireValid: true },
            ],
            title: "Nouveau calendrier",
            fields: [
                {
                    compact: true,
                    name: "name",
                    placeholder: "Nom",
                    required: true,
                    type: "input_string",
                },
                {
                    compact: true,
                    type: "quill",
                    placeholder: "Description (optionnelle)",
                    name: "description",
                },
            ],
            task: 27,
        });
    }
    /**
     * Creates event in calendar
     * @param {Date} date
     */
    newEvent(date) {
        let eventDate = dateGetClosestQuarter(date);
        const start = toMYSQLDTString(eventDate);
        eventDate.setHours(eventDate.getHours() + 1);
        const end = toMYSQLDTString(eventDate);
        // send server new event data: start,end
        socket.send({
            f: 25,
            c: parseInt(this.active),
            e: {
                start: start,
                end: end,
                created: toMYSQLDTString(new Date()),
                summary: "New event",
            },
        });
    }
    static parse(data) {
        const cal = BopCal.bopcals[0];
        if (data.response?.fail) {
            console.error(data.response.fail);
            return msg.new({
                content: data.response.message,
                type: "warning",
            });
        }
        switch (data.f) {
            case 21: // get calendars and static values
                for (const [key, value] of Object.entries(data.response))
                    cal[key] = value;
                for (const key of Object.keys(cal.calendars))
                    cal.minicalAddCalendar(key);
                break;
            case 22: // get components in range
                if (data.response)
                    for (const [calendar, events] of Object.entries(
                        data.response
                    ))
                        for (const event of events)
                            cal.addComponent(calendar, event);
                break;
            case 25: // new component
                cal.addComponent(data.c, data.event);
                break;
            case 27: // new calendar
                cal.addCalendar(data.response);
                Modal.modals.filter((x) => x.task === 27)[0].close();
                break;
            case 29: // remove calendar
                if (data.x) {
                    console.log(
                        `Calendar #${data.c} has been removed by owner.`
                    );
                    // message calendar has been removed by owner
                    msg.new({
                        type: "warning",
                        content: `Le calendrier ${
                            cal.calendars[data.c].name
                        } a été supprimé.`,
                    });
                    // remove calendar and events from app
                    cal.calRemove(data.c);
                }
                break;
            case 32: {
                // update component's range
                let component = cal.calendars[data.c].components[data.u];
                component.modified = data.m;
                component.start = new Date(`${data.s.replace(" ", "T")}Z`);
                component.end = new Date(`${data.e.replace(" ", "T")}Z`);
                cal.placeComponent(data.c, data.u);
                break;
            }
            case 33: {
                // delete component
                const component = cal.calendars[data.c].components[data.u];
                for (let element of Object.values(component.elements))
                    element.remove();
                delete cal.calendars[data.c].components[data.u];
                cal.modal.close();
                msg.close();
                msg.new({
                    content: "L'évenement à été supprimé.",
                    type: "success",
                });
                break;
            }
            case 34: // update component
                cal.componentUpdate(data);
                break;
        }
    }
    /**
     * Places/updates component in bigcal according to component's data.
     * @param {Number} idcal
     * @param {String} uid - Component object stored in bigcal.
     * @param {Boolean} [move] - If set, adds .focus to component at date.
     */
    placeComponent(idcal, uid, move) {
        let component = this.calendars[idcal].components[uid];
        // get dates between start & end
        const dates = getDaysBetweenDates(component.start, component.end),
            datesStrings = dates.map((x) => toYYYYMMDDString(x));
        // delete unused elements
        for (const datestring of Object.keys(component.elements)) {
            if (!datesStrings.includes(datestring)) {
                component.elements[datestring].remove();
                delete component.elements[datestring];
            }
        }
        // for each day, set start/end according to event start/end
        for (const day of dates) {
            const nextDay = new Date(
                    day.getFullYear(),
                    day.getMonth(),
                    day.getDate() + 1
                ),
                dateString = toYYYYMMDDString(day);
            let classes = [],
                top,
                height,
                el = component.elements[dateString] ?? undefined;
            const dayWrapper = el
                ? el.parentNode.parentNode
                : this.bigcal.years[day.getFullYear()].months[
                      day.getMonth()
                  ].querySelector(`[data-date="${day.getDate()}"]:not(.fade)`);
            if (!component.allday) {
                top =
                    component.start < day
                        ? "0px"
                        : this.calcEventTop(component.start);
                height =
                    component.end < nextDay
                        ? this.calcEventHeight(
                              component.start < day ? day : component.start,
                              component.end
                          )
                        : this.calcEventHeight(
                              component.start < day ? day : component.start,
                              nextDay
                          );
            }
            // if el of event exists
            if (el) {
                el.getElementsByTagName("span")[0].textContent =
                    new Intl.DateTimeFormat("fr", {
                        timeStyle: "short",
                    }).format(component.start);
                // if parent = allday
                if (el.parentNode === dayWrapper.children[1]) {
                    // if !event.allday, move element to dayWrapper, set top / height
                    if (!component.allday) {
                        dayWrapper.lastElementChild.append(el);
                        el.style.top = top;
                        el.style.height = height;
                    }
                }
                // if parent = dayWrapper
                else {
                    // if event.allday, move element to allday, set start/end classes
                    if (component.allday) {
                        dayWrapper.children[1].append(el);
                    }
                    // else update top/height if necessary
                    else {
                        el.style.top = top;
                        el.style.height = height;
                    }
                }
            }
            // else create it
            else {
                el = document.createElement("div");
                let hour = document.createElement("span"),
                    summary = document.createElement("span"),
                    handleStart = document.createElement("div"),
                    handleEnd = document.createElement("div");
                hour.textContent = new Intl.DateTimeFormat("fr", {
                    timeStyle: "short",
                }).format(component.start);
                summary.textContent = component.summary ?? "New event";
                setElementAttributes(el, {
                    "data-cal": idcal,
                    "data-uid": uid,
                });
                el.style.setProperty(
                    "--event-color",
                    this.calendars[idcal].color
                );
                el.append(handleStart, hour, summary, handleEnd);
                component.elements[dateString] = el;
                if (component.allday) {
                    dayWrapper.children[1].append(el);
                    el.className = classes;
                } else {
                    dayWrapper.lastElementChild.append(el);
                    el.style.top = top;
                    el.style.height = height;
                }
            }
            if (move) el.classList.add("focus");
            component.start >= day
                ? el.classList.add("start")
                : el.classList.remove("start");
            component.end < nextDay
                ? el.classList.add("end")
                : el.classList.remove("end");
            // display time if element's duration > 30min
            el.getElementsByTagName("span")[0].style.display =
                getMinutesBetweenDates(component.start, component.end) <= 30
                    ? "none"
                    : "";
        }
    }
    setActiveCal(idcal) {
        if (this.active === idcal) return;
        console.log(`Active cal set to cal#${idcal}`);
        this.active = idcal;
        for (const [key, value] of Object.entries(this.calendars)) {
            key === idcal
                ? value.li.classList.add("active")
                : value.li?.classList.remove("active");
        }
    }
    /**
     * Hides or show components from specified calendar.
     * @param {Number} idcal
     * @param {Boolean} show
     */
    toggleCalVisibility(idcal, visible = true) {
        this.calendars[idcal].visible = visible;
        this.calendars[idcal].li.querySelector('[type="checkbox"]').checked =
            visible;
        if (this.calendars[idcal].components)
            for (const component of Object.values(
                this.calendars[idcal].components
            ))
                for (const element of Object.values(component.elements))
                    visible
                        ? element.classList.remove("hide")
                        : element.classList.add("hide");
        // set visible = show to user_has_calendar;
        socket.send({ f: 31, c: idcal, v: visible ? 1 : 0 });
    }
    // addFullCalEvent() {
    //     // manage batch rendering ?
    //     // fullcal event :
    //     // id
    //     // start
    //     // end
    //     // rrule
    //     // all day
    //     // title
    //     // url
    //     // extendedProps {}
    // }
    // addCalendarEvent() {
    //     // VEVENT
    //     // UID
    //     // CREATED: UTC
    //     // LAST-MODIFIED: UTC
    //     // DTSTAMP: UTC (set by caldav server when adding event?)
    //     // DTSTART: TZ or UTC
    //     // DTEND OR DURATION
    //     // TRANSP: OPAQUE OR TRANSPARENT (for busy time searches, not style related!)
    //     // SUMMARY: text
    //     // CATEGORIES: ~ tags/groups, e.g. CATEGORIES:ANNIVERSARY,PERSONAL,SPECIAL OCCASION
    //     // CLASS: related to securing access to event, allows non standard values, must be completed by calendar agent logic, does nothing alone. e.g. PUBLIC (default value), PRIVATE, CONFIDENTIAL...
    //     // ORGANIZER: CN (display name), MAILTO (email address). e.g. ORGANIZER;CN=John Smith:MAILTO:jsmith@host.com
    //     // ATTENDEE: CN=, MAILTO:, MEMBER=, DELEGATED-TO=, DELEGATED-FROM=,CUTYPE=
    //     // RELATED-TO: to figure out how it works.
    //     //
    //     // VALARM (nested in VEVENT or VTODO)
    //     // UID
    //     // ACTION: AUDIO, DISPLAY, EMAIL, PROCEDURE
    //     // TRIGGER: DURATION, UTC, START (requires DTSTART), END (requires DTEND, DTSTART & DURATION, or DUE in case of VTODO). e.g. -PT15M (15 min before), -P7W (7 weeks before)
    //     // ATTACH: audio component (unique), message attachments, local procedure (unique).
    //     // DESCRIPTION: display content, email body.
    //     // SUMMARY: email subject
    //     // ATTENDEE: email address (one per ATTENDEE property)
    //     // DURATION: e.g. PT5M (5 minutes)
    //     // REPEAT: integer, specifies number of times the alarm is to repeat, requires DURATION.
    //     //
    //     //      ; the following are optional,
    //     //      ; but MUST NOT occur more than once
    //     //      class / created / description / dtstart / geo /
    //     //      last-mod / location / organizer / priority /
    //     //      dtstamp / seq / status / summary / transp /
    //     //      uid / url / recurid /
    //     //      ; either 'dtend' or 'duration' may appear in
    //     //      ; a 'eventprop', but 'dtend' and 'duration'
    //     //      ; MUST NOT occur in the same 'eventprop'
    //     //      dtend / duration /
    //     //      ; the following are optional,
    //     //      ; and MAY occur more than once
    //     //      attach / attendee / categories / comment /
    //     //      contact / exdate / exrule / rstatus / related /
    //     //      resources / rdate / rrule / x-prop
    // }
}

// big-cal on click:
// - create untitled/pretitled new event with loading class (not editable, color faded)
// - send event data to server
// - server returns new event
// - remove loading class (shouldn't be visible to user unless problem with server).
// on button down:
// - drag event, snapping on time divisions while dragging
// on release:
// - loading class
// - send server new data
// - server returns event
// - remove loading class
