/**
 * Passes POST request with body of the request as parameter.
 * @param {String} body - Body of the POST request
 * @returns {String} "ok" if request successful, else "error"
 */
async function fetchPost(body) {
    let d = await fetch("/", {
        method: "POST",
        headers: {
            "Content-Type":
                'application/x-www-form-urlencoded; charset="utf-8"',
        },
        body: body,
    });
    if (d.ok) {
        return "ok";
    } else {
        alert("HTTP-Error: " + d.status);
        return "error";
    }
}
/**
 * Returns request's response as string.
 * @param {String} body - Body of the POST request
 * @returns {String} - Response echoed from request, else "error"
 */
async function fetchPostText(body) {
    let d = await fetch("/", {
        method: "POST",
        headers: {
            "Content-Type":
                'application/x-www-form-urlencoded; charset="utf-8"',
        },
        body: body,
    });
    if (d.ok) {
        let c = await d.text();
        return c;
    }
    return alert("HTTP-Error: " + d.status);
}
/**
 * Returns request's response as JSON.
 * @param {String} body - Body of the POST request
 * @param {Boolean} [json=false] - False: body is a string, True: body is an object.
 * @returns {String} - JSON encoded response echoed from request
 */
async function fetchPostJSON(body, json = false) {
    const type = json
        ? "application/json"
        : 'application/x-www-form-urlencoded; charset="utf-8"';
    let res;
    res = await fetch("/", {
        method: "POST",
        headers: {
            "Content-Type": type,
            Accept: "application/json",
        },
        body: body,
    })
        .then((Response) => Response.json())
        .then((data) => {
            if (data["error"]) throw data;
            else if (data["success"]) {
                sweetAlert({
                    content: data["success"],
                    type: "success",
                });
            }
            return data;
        })
        .catch((error) => {
            console.error(error["error"]);
            if (error["data"]) console.debug(error["data"]);
            sweetAlert({
                content: error["fail"],
                type: "danger",
            });
        });
    return res;
}
