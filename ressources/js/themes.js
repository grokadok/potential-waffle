// Functions

function themifySetText() {
    const themifyText = document.getElementById("theme-text");
    if (typeof themifyText !== "undefined" && themifyText !== null) {
        const body = document.body,
            themifyDark = document.getElementById("darkIcon"),
            themifyLight = document.getElementById("lightIcon");
        if (body.classList.contains("light" || "set-light")) {
            themifyText.textContent = "Light";
            themifyDark.style.display = "none";
            themifyLight.style.display = "block";
        } else if (body.classList.contains("dark" || "set-dark")) {
            themifyText.textContent = "Dark";
            themifyDark.style.display = "block";
            themifyLight.style.display = "none";
        }
    }
}

function themify() {
    const body = document.body;
    if (body.classList.contains("light")) {
        body.classList.replace("light", "dark");
        localStorage.setItem("theme", "dark");
    } else {
        body.classList.replace("dark", "light");
        localStorage.setItem("theme", "light");
    }
    themifySetText();
}

// Apply the cached theme on reload
function themifyCache() {
    const theme = localStorage.getItem("theme"),
        isSolar = localStorage.getItem("isSolar"),
        body = document.body;

    if (theme && body.classList.contains("light")) {
        isSolar && body.classList.add("solar");
        if (theme !== "light") {
            body.classList.replace("light", theme);
            themifySetText();
        }
    }
}

// Event listeners

function themifyListeners() {
    const themifyButton = document.getElementById("themify"),
        solarButton = document.getElementById("solar"),
        body = document.body;

    themifyButton.addEventListener("click", themify);

    solarButton.addEventListener("click", function () {
        body.classList.toggle("solar");
        body.classList.contains("solar")
            ? localStorage.setItem("isSolar", true)
            : localStorage.removeItem("isSolar");
    });
    themifySetText();
}
