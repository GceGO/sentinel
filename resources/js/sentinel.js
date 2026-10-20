/*
 * GCEGO Sentinel page behavior. No dependencies, no inline code (works
 * under a Content Security Policy), progressive: every form works without it.
 *
 *  - appearance: light / dark / system, shared with gcego/chroma's
 *    preference (same storage key), applied before first paint;
 *  - show / hide password, password strength hints;
 *  - submit loading state and double-submit protection;
 *  - copy and print recovery codes; focus on the error summary;
 *  - passkeys: runs the WebAuthn ceremonies (navigator.credentials) between
 *    Sentinel's option and verification endpoints. The server issues the
 *    challenge and verifies the result; the script only carries them.
 *
 * It never reads, stores or sends a password, code or token.
 */
(function () {
    "use strict";

    var root = document.documentElement;
    var data = root.dataset || {};
    var modes = ["light", "dark", "system"];
    var key = data.sentinelStorageKey || "gcego.appearance";
    var allowSwitching = data.sentinelSwitching === "true";
    var fallback = modes.indexOf(data.sentinelTheme) === -1 ? "system" : data.sentinelTheme;
    var media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

    function stored() {
        if (!allowSwitching) {
            return fallback;
        }

        try {
            var value = window.localStorage.getItem(key);

            return modes.indexOf(value) === -1 ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    var mode = stored();

    function each(selector, callback, scope) {
        Array.prototype.forEach.call((scope || document).querySelectorAll(selector), callback);
    }

    function syncAppearanceButtons() {
        each("[data-sentinel-appearance-option]", function (button) {
            button.setAttribute("aria-pressed", button.getAttribute("data-sentinel-appearance-option") === mode ? "true" : "false");
        });
    }

    function applyAppearance() {
        var dark = mode === "dark" || (mode === "system" && !!media && media.matches);

        root.classList.toggle("dark", dark);
        root.style.colorScheme = dark ? "dark" : "light";
        root.setAttribute("data-sentinel-appearance", mode);
        syncAppearanceButtons();
    }

    function setAppearance(value) {
        if (modes.indexOf(value) === -1) {
            return;
        }

        mode = value;

        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // Storage unavailable (private mode): the choice lasts for this page.
        }

        applyAppearance();
    }

    applyAppearance();

    if (media && media.addEventListener) {
        media.addEventListener("change", function () {
            if (mode === "system") {
                applyAppearance();
            }
        });
    }

    if (!window.GcegoAppearance) {
        window.GcegoAppearance = {
            get: function () {
                return mode;
            },
            set: setAppearance,
        };
    }

    function announce(message) {
        var live = document.querySelector("[data-sentinel-live]");

        if (!live || !message) {
            return;
        }

        live.textContent = "";
        window.setTimeout(function () {
            live.textContent = message;
        }, 50);
    }

    function strength(value, min) {
        var met = {
            min: value.length >= min,
            mixedCase: /[a-z]/.test(value) && /[A-Z]/.test(value),
            letters: /\p{L}/u.test(value),
            numbers: /\d/.test(value),
            symbols: /[^\p{L}\d\s]/u.test(value),
        };
        var score = 0;

        if (value.length > 0) {
            score = 1;
        }
        if (met.min) {
            score = 2;
        }
        if (met.min && (met.mixedCase || met.numbers) && value.length >= min + 2) {
            score = 3;
        }
        if (met.min && met.mixedCase && met.numbers && met.symbols && value.length >= min + 4) {
            score = 4;
        }

        return { met: met, score: score };
    }

    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
        } else {
            callback();
        }
    }

    function reveal(scope) {
        each("[data-sentinel-js][hidden]", function (element) {
            element.hidden = false;
        }, scope);
        syncAppearanceButtons();
    }

    ready(function () {
        reveal();

        var summary = document.querySelector("[data-sentinel-error-summary]");

        if (summary) {
            summary.focus();
        }

        // Livewire morphs the account security panels: reveal controls it adds.
        if (window.MutationObserver) {
            new MutationObserver(function () {
                reveal();
            }).observe(document.body, { childList: true, subtree: true });
        }
    });


    // ---- Passkeys (WebAuthn) ------------------------------------------------

    function fromBase64Url(value) {
        var base64 = String(value).replace(/-/g, "+").replace(/_/g, "/");
        var padded = base64 + "===".slice((base64.length + 3) % 4);
        var binary = window.atob(padded);
        var bytes = new Uint8Array(binary.length);

        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        return bytes.buffer;
    }

    function toBase64Url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = "";

        for (var i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }

        return window.btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
    }

    function passkeysSupported() {
        return !!(window.PublicKeyCredential && navigator.credentials && window.fetch);
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var input = document.querySelector('input[name="_token"]');

        return meta ? meta.getAttribute("content") : (input ? input.value : "");
    }

    function requestJson(url, method, body) {
        return window.fetch(url, {
            method: method,
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrfToken(),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) {
                    var errors = data && data.errors ? data.errors : {};
                    var first = Object.keys(errors).map(function (key) {
                        return errors[key][0];
                    })[0];

                    throw new Error(first || (data && data.message) || "");
                }

                return data;
            });
        });
    }

    function credentialDescriptors(list) {
        return (list || []).map(function (descriptor) {
            return { type: descriptor.type, id: fromBase64Url(descriptor.id), transports: descriptor.transports };
        });
    }

    function serializeCredential(credential) {
        var response = credential.response;
        var serialized = {
            id: credential.id,
            rawId: toBase64Url(credential.rawId),
            type: credential.type,
            response: { clientDataJSON: toBase64Url(response.clientDataJSON) },
        };

        if (response.attestationObject) {
            serialized.response.attestationObject = toBase64Url(response.attestationObject);
            serialized.response.transports = response.getTransports ? response.getTransports() : [];
        } else {
            serialized.response.authenticatorData = toBase64Url(response.authenticatorData);
            serialized.response.signature = toBase64Url(response.signature);
            serialized.response.userHandle = response.userHandle ? toBase64Url(response.userHandle) : null;
        }

        return serialized;
    }

    function setStatus(element, message) {
        if (element) {
            element.textContent = message || "";
        }
    }

    function passkeyLogin(container, button) {
        var status = container.querySelector("[data-sentinel-passkey-status]");

        if (!passkeysSupported()) {
            setStatus(status, status ? status.getAttribute("data-unsupported") : "");

            return;
        }

        var remember = document.querySelector('input[name="remember"]');
        button.disabled = true;
        setStatus(status, status ? status.getAttribute("data-working") : "");

        requestJson(container.getAttribute("data-options-url"), "GET").then(function (data) {
            var options = data.options;
            options.challenge = fromBase64Url(options.challenge);
            options.allowCredentials = credentialDescriptors(options.allowCredentials);

            return navigator.credentials.get({ publicKey: options });
        }).then(function (credential) {
            return requestJson(container.getAttribute("data-login-url"), "POST", {
                credential: serializeCredential(credential),
                remember: !!(remember && remember.checked),
            });
        }).then(function (data) {
            window.location.assign(data.redirect || "/");
        }).catch(function (error) {
            button.disabled = false;
            setStatus(status, (error && error.message) || (status ? status.getAttribute("data-failed") : ""));
        });
    }

    function livewireDispatch(name) {
        if (window.Livewire && window.Livewire.dispatch) {
            window.Livewire.dispatch(name);
        }
    }

    window.addEventListener("gcego-sentinel-passkey-register", function (event) {
        var container = document.querySelector("[data-sentinel-passkey-register]");
        var name = event.detail && event.detail.name ? event.detail.name : "";

        if (!container) {
            return;
        }

        var status = container.querySelector("[data-sentinel-passkey-status]");

        if (!passkeysSupported()) {
            setStatus(status, container.getAttribute("data-unsupported"));
            livewireDispatch("gcego-sentinel-passkey-failed");

            return;
        }

        setStatus(status, "");

        requestJson(container.getAttribute("data-options-url"), "GET").then(function (data) {
            var options = data.options;
            options.challenge = fromBase64Url(options.challenge);
            options.user.id = fromBase64Url(options.user.id);
            options.excludeCredentials = credentialDescriptors(options.excludeCredentials);

            return navigator.credentials.create({ publicKey: options });
        }).then(function (credential) {
            return requestJson(container.getAttribute("data-store-url"), "POST", {
                name: name,
                credential: serializeCredential(credential),
            });
        }).then(function () {
            livewireDispatch("gcego-sentinel-passkey-registered");
        }).catch(function (error) {
            setStatus(status, (error && error.message) || container.getAttribute("data-failed"));
            livewireDispatch("gcego-sentinel-passkey-failed");
        });
    });

    document.addEventListener("click", function (event) {
        var target = event.target && event.target.closest ? event.target : null;

        if (!target) {
            return;
        }

        var passkey = target.closest("[data-sentinel-passkey-start]");

        if (passkey) {
            var container = passkey.closest("[data-sentinel-passkey-login]");

            if (container) {
                passkeyLogin(container, passkey);
            }

            return;
        }

        var toggle = target.closest("[data-sentinel-password-toggle]");

        if (toggle) {
            var input = document.getElementById(toggle.getAttribute("aria-controls"));

            if (input) {
                var show = input.type === "password";

                input.type = show ? "text" : "password";
                toggle.setAttribute("aria-pressed", show ? "true" : "false");
                toggle.setAttribute("aria-label", show ? toggle.getAttribute("data-hide-label") : toggle.getAttribute("data-show-label"));
                each("[data-sentinel-icon]", function (icon) {
                    icon.classList.toggle("hidden", (icon.getAttribute("data-sentinel-icon") === "hide") !== show);
                }, toggle);
                input.focus();
            }

            return;
        }

        var appearance = target.closest("[data-sentinel-appearance-option]");

        if (appearance) {
            setAppearance(appearance.getAttribute("data-sentinel-appearance-option"));
            announce(appearance.getAttribute("aria-label"));

            return;
        }

        var copy = target.closest("[data-sentinel-copy]");

        if (copy) {
            var source = document.getElementById(copy.getAttribute("data-sentinel-copy"));

            if (source && navigator.clipboard) {
                navigator.clipboard.writeText(source.innerText.trim()).then(function () {
                    announce(copy.getAttribute("data-copied"));
                });
            }

            return;
        }

        if (target.closest("[data-sentinel-print]")) {
            window.print();
        }
    });

    document.addEventListener("submit", function (event) {
        var form = event.target;

        if (!form || !form.hasAttribute || !form.hasAttribute("data-sentinel-form")) {
            return;
        }

        if (form.hasAttribute("data-loading")) {
            event.preventDefault();

            return;
        }

        form.setAttribute("data-loading", "");
        form.setAttribute("aria-busy", "true");
    });

    document.addEventListener("input", function (event) {
        var input = event.target;

        if (!input || !input.hasAttribute || !input.hasAttribute("data-sentinel-strength")) {
            return;
        }

        var result = strength(input.value, parseInt(input.getAttribute("data-min"), 10) || 8);
        var bar = document.getElementById(input.getAttribute("data-sentinel-strength"));
        var rules = document.getElementById(input.getAttribute("data-sentinel-rules"));

        if (bar) {
            bar.setAttribute("data-level", String(result.score));
        }

        if (rules) {
            each("[data-rule]", function (rule) {
                rule.toggleAttribute("data-met", result.met[rule.getAttribute("data-rule")] === true);
            }, rules);
        }
    });

    // Back/forward cache: a restored page must not stay "loading".
    window.addEventListener("pageshow", function () {
        each("[data-sentinel-form][data-loading]", function (form) {
            form.removeAttribute("data-loading");
            form.removeAttribute("aria-busy");
        });
    });
})();
