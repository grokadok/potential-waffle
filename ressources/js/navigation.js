document.addEventListener("mousemove", (e) => {
    if (
        e.target &&
        document.activeElement !== e.target &&
        e.target.nodeName.toLowerCase() === "button" &&
        document.activeElement.nodeName.toLocaleLowerCase() === "button"
    )
        e.target.focus();
    // switch (document.activeElement.tagName.toLowerCase()) {
    //     case "button":
    //         if (
    //             document.activeElement !== e.target &&
    //             e.target.tagName.toLowerCase() === "button"
    //         )
    //             e.target.focus();
    //         break;
    //     default:
    //         break;
    // }
});
