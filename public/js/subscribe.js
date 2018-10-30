function formToJSON(elements) {
    return [].reduce.call(
        elements,
        (data, element) => {
            let regex = /^(.+)\[\]$/.exec(element.name);
            if (regex !== null) {
                console.log(regex);

                let name = regex[1];

                if (typeof data[name] === "undefined") {
                    data[name] = [];
                }

                data[name].push(element.value);
            } else {
                data[element.name] = $.trim(element.value).length > 0 ? element.value : null;
            }

            return data;
        },
        {}
    );
}

function subscribe() {
    let data = formToJSON($("#subscribe-form").serializeArray());

    fetch(`/api/subscribe/${list}`, {
        method: "POST",
        cache: "no-cache",
        headers: {
            "Content-Type": "application/json; charset=utf-8"
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(json => {
            console.log(json);

            if (Number.isInteger(json.status) && typeof json.title !== "undefined" && json.detail !== "undefined") {
                let html = `<strong>${json.title}</strong><br>` + json.detail;

                $("#result > .text-danger").html(html);
                $("#result, #result > .text-danger").show();
                $("#btn-submit").prop("disabled", false);
            } else {
                $("#result, #result > .text-success").show();
                $("#btn-submit").prop("disabled", false);
            }
        });
}

$(document).ready(() => {
    $("#subscribe-form").on("submit", async event => {
        event.preventDefault();

        const inputOSMUsername = typeof osmUsername !== "undefined" ? $(`input[name=${osmUsername}]`) : null;

        // $("#btn-submit").prop("disabled", true);
        $(inputOSMUsername).removeClass("is-invalid is-valid");
        $("#result, #result > .text-success, #result > .text-danger").hide();

        if (inputOSMUsername !== null && $(inputOSMUsername).length > 0) {
            let username = $(inputOSMUsername).val();
            let required = $(inputOSMUsername).prop("required");

            if (username.length > 0) {
                fetch(`/api/osm-user/${username}`)
                    .then(response => {
                        if (response.status === 404) {
                            $(inputOSMUsername).addClass("is-invalid");

                            throw `The OpenStreetMap username "${username}" does not exists.`;
                        }
                    })
                    .then(
                        () => {
                            subscribe();
                        },
                        reason => {
                            $("#result > .text-danger").text(reason);
                            $("#result, #result > .text-danger").show();
                            $("#btn-submit").prop("disabled", false);
                        }
                    );
            } else {
                subscribe();
            }
        }
    });
});
