/**
 * CSV import results modal for OJS 3.4.
 *
 * ImportExportPage does not include the dialog mixin, so this script opens the
 * modal through page.$modal.show() with an inline component (3.4 Dialog API).
 */
(function() {
    var pluginConfig = null;
    var MODAL_NAME = "csvImportResults";

    function init() {
        pluginConfig = window.csvImportPluginConfig;
        if (!pluginConfig || !window.pkp || !pkp.eventBus) {
            return;
        }

        pkp.localeKeys["common.saving"] = pluginConfig.labels.importing || "Processing";
        pkp.localeKeys["form.saved"] = pluginConfig.labels.imported || "Saved";

        pkp.eventBus.$on("form-success", function(fId, response) {
            if (fId !== pluginConfig.formId) {
                return;
            }

            var labels = pluginConfig.labels;
            var downloadBaseUrl = pluginConfig.downloadBaseUrl;
            var bodyHtml = buildModalContent(response, labels, downloadBaseUrl);
            var title = response.resultDryMode ? labels.dryModeTitle : labels.importCompleteTitle;
            var closeLabel = (pkp.localeKeys && pkp.localeKeys["common.close"]) || "Close";
            var uuid = response.uuid || null;
            var cleanedUp = false;

            function closeOnce() {
                if (cleanedUp) {
                    return;
                }
                cleanedUp = true;
                callCleanup(uuid);
            }

            openResultsModal(getPageInstance(), title, bodyHtml, closeLabel, closeOnce);
        });
    }

    function getPageInstance() {
        return pkp.registry && pkp.registry._instances
            ? pkp.registry._instances.app
            : null;
    }

    function openResultsModal(page, title, bodyHtml, closeLabel, onClose) {
        if (page && typeof page.openDialog === "function") {
            page.openDialog({
                name: MODAL_NAME,
                title: title,
                message: bodyHtml,
                actions: [{
                    label: closeLabel,
                    isPrimary: true,
                    callback: function() {
                        onClose();
                        page.$modal.hide(MODAL_NAME);
                    }
                }],
                close: onClose
            }, {
                scrollable: true,
                width: "75%"
            });
            widenDialogPanel();
            return;
        }

        if (page && page.$modal) {
            var ModalComponent = {
                props: ["title", "message", "closeLabel", "modalName"],
                template:
                    "<div class=\"modal__panel modal__panel--dialog\" data-csv-import-results-root>" +
                    "  <div class=\"modal__header\">" +
                    "    <h2 class=\"modal__title\">{{ title }}</h2>" +
                    "    <button type=\"button\" class=\"modal__closeButton\" @click=\"closeModal\">" +
                    "      <span aria-hidden=\"true\">×</span>" +
                    "      <span class=\"-screenReader\">{{ closeLabel }}</span>" +
                    "    </button>" +
                    "  </div>" +
                    "  <div class=\"modal__content\" style=\"max-height:70vh;overflow-y:auto;\">" +
                    "    <div v-html=\"message\"></div>" +
                    "  </div>" +
                    "  <div class=\"modal__footer\">" +
                    "    <button type=\"button\" class=\"pkpButton pkpButton--isPrimary\" @click=\"closeModal\">{{ closeLabel }}</button>" +
                    "  </div>" +
                    "</div>",
                methods: {
                    closeModal: function() {
                        onClose();
                        this.$modal.hide(this.modalName);
                    }
                }
            };

            page.$modal.show(
                ModalComponent,
                {
                    title: title,
                    message: bodyHtml,
                    closeLabel: closeLabel,
                    modalName: MODAL_NAME
                },
                {
                    name: MODAL_NAME,
                    height: "auto",
                    scrollable: true,
                    classes: "v--modal v--modal-dialog",
                    width: "75%"
                },
                {
                    closed: onClose
                }
            );
            return;
        }

        openDomModal(title, bodyHtml, closeLabel, onClose);
    }

    function openDomModal(title, bodyHtml, closeLabel, onClose) {
        var overlay = document.createElement("div");
        overlay.setAttribute("data-csv-import-results-overlay", "1");
        overlay.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;display:flex;align-items:flex-start;justify-content:center;padding:2rem;overflow-y:auto;";

        var panel = document.createElement("div");
        panel.setAttribute("data-csv-import-results-root", "1");
        panel.style.cssText = "background:#fff;width:75%;max-width:75%;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.2);";

        panel.innerHTML =
            "<div class=\"modal__header\" style=\"display:flex;align-items:center;padding:0.5rem 1rem;border-bottom:1px solid #ddd;\">" +
            "  <h2 style=\"margin:0;font-size:1rem;flex:1;\">" + escapeHtml(title) + "</h2>" +
            "  <button type=\"button\" data-csv-import-close style=\"border:none;background:transparent;font-size:1.5rem;cursor:pointer;\">×</button>" +
            "</div>" +
            "<div class=\"modal__content\" style=\"padding:1rem;max-height:70vh;overflow-y:auto;\">" + bodyHtml + "</div>" +
            "<div class=\"modal__footer\" style=\"padding:1rem;text-align:right;border-top:1px solid #ddd;\">" +
            "  <button type=\"button\" data-csv-import-close class=\"pkpButton pkpButton--isPrimary\">" + escapeHtml(closeLabel) + "</button>" +
            "</div>";

        overlay.appendChild(panel);
        document.body.appendChild(overlay);

        function closeModal() {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            onClose();
        }

        overlay.addEventListener("click", function(event) {
            if (event.target === overlay) {
                closeModal();
            }
        });

        panel.querySelectorAll("[data-csv-import-close]").forEach(function(button) {
            button.addEventListener("click", closeModal);
        });
    }

    function widenDialogPanel() {
        var attempts = 0;

        function tryApply() {
            attempts++;
            var marker = document.querySelector("[data-csv-import-results-root]");
            var panel = (marker && marker.closest(".v--modal"))
                || document.querySelector(".v--modal-overlay:last-child .v--modal");

            if (panel) {
                panel.style.setProperty("width", "75%", "important");
                panel.style.setProperty("max-width", "75%", "important");
                return;
            }

            if (attempts < 60) {
                requestAnimationFrame(tryApply);
            }
        }

        requestAnimationFrame(tryApply);
    }

    function buildModalContent(response, labels, downloadBaseUrl) {
        var html = "";

        html += "<div data-csv-import-results style='display:none;'></div>";
        html += buildIntro(response, labels);

        html += "<div style='display:flex; gap:1.5rem; flex-wrap:wrap; margin-bottom:1.5rem; padding:1rem; background:rgba(234,237,238,0.3); border:1px solid #BBBBBB; border-radius:4px;'>";
        html += summaryBadge(labels.filesProcessed, response.resultFilesProcessed, "#222222");
        html += summaryBadge(labels.totalRows, response.resultTotalRows, "#222222");
        html += summaryBadge(labels.successfulRows, response.resultSuccessfulRows, "#00B24E");
        html += summaryBadge(labels.createdRows, response.resultCreatedRows || 0, "#006798");
        html += summaryBadge(labels.updatedRows, response.resultUpdatedRows || 0, "#0082BF");
        html += summaryBadge(labels.failedRows, response.resultFailedRows, response.resultFailedRows > 0 ? "#D00A6C" : "#222222");
        html += "</div>";

        if (response.resultPerFile && response.resultPerFile.length > 0) {
            for (var i = 0; i < response.resultPerFile.length; i++) {
                html += buildFileSection(response.resultPerFile[i], response.uuid, downloadBaseUrl, labels);
            }
        }

        return html;
    }

    function buildIntro(response, labels) {
        var isUsers = response.resultImportType === "users";
        var isDryMode = !!response.resultDryMode;

        var intro;
        if (isUsers) {
            intro = isDryMode ? labels.introUsersDryMode : labels.introUsers;
        } else {
            intro = isDryMode ? labels.introIssuesDryMode : labels.introIssues;
        }

        var accent = isDryMode ? "#0082BF" : (response.resultFailedRows > 0 ? "#D00A6C" : "#00B24E");
        var hasInvalidFiles = response.resultInvalidFiles && response.resultInvalidFiles.length > 0;

        var html = "";
        html += "<div style='margin-bottom:1.5rem; padding:1rem 1.25rem; background:#F3F6F9; border-left:4px solid " + accent + "; border-radius:2px; font-size:0.875rem; line-height:1.5rem; color:#222222;'>";
        html += "<p style='margin:0 0 0.75rem;'>" + intro + "</p>";
        html += "<p style='margin:0; color:#505050;'>" + labels.legend + "</p>";
        if (hasInvalidFiles) {
            html += "<p style='margin:0.75rem 0 0; color:#505050;'>" + labels.invalidFilesHint + "</p>";
        }
        if (isUsers && (response.resultUpdatedRows || 0) > 0 && labels.updatedUsersExplanation) {
            html += "<p style='margin:0.75rem 0 0; padding:0.5rem 0.75rem; background:#E6F2F8; border-left:3px solid #0082BF; border-radius:2px; color:#002C40; font-size:0.8125rem; line-height:1.4rem;'>" + labels.updatedUsersExplanation + "</p>";
        }
        html += "</div>";

        return html;
    }

    function summaryBadge(label, value, color) {
        return "<div style='text-align:center; flex:1; min-width:80px;'>" +
            "<div style='font-size:1.5rem; font-weight:700; color:" + color + "; line-height:2rem;'>" + value + "</div>" +
            "<div style='font-size:0.75rem; font-weight:400; color:#505050; line-height:1rem;'>" + label + "</div>" +
            "</div>";
    }

    function buildFileSection(file, uuid, downloadBaseUrl, labels) {
        var html = "";

        html += "<div style='margin-top:1rem; padding:0.5rem 0.75rem; background:#002C40; color:#FFFFFF; border-radius:4px 4px 0 0; font-family:monospace; font-size:0.875rem; font-weight:700;'>";
        html += "=== " + escapeHtml(file.filename) + " ===";
        html += "</div>";

        if (file.updatedUsers && file.updatedUsers.length > 0) {
            html += "<div style='margin-top:0.5rem; border:1px solid #BBBBBB; overflow-x:auto;'>";
            html += "<table style='width:100%; border-collapse:separate; border-spacing:0; font-family:monospace; font-size:0.75rem;'>";
            html += "<thead><tr style='background:#FFFFFF;'>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>" + (labels.updatedUsersSection || "UPDATED USERS") + "</th>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; width:80px; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>STATUS</th>";
            html += "</tr></thead><tbody>";

            for (var k = 0; k < file.updatedUsers.length; k++) {
                var email = file.updatedUsers[k];
                var updatedRowBg = k % 2 === 1 ? "background:rgba(234,237,238,0.3);" : "";
                html += "<tr style='" + updatedRowBg + "'>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#222222;'>" + escapeHtml(email) + "</td>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#006798; font-weight:700;'>UPDATED</td>";
                html += "</tr>";
            }

            html += "</tbody></table>";
            html += "</div>";
        }

        if (file.errors && file.errors.length > 0) {
            html += "<div style='margin-top:0.5rem; border:1px solid #BBBBBB; overflow-x:auto;'>";
            html += "<table style='width:100%; border-collapse:separate; border-spacing:0; font-family:monospace; font-size:0.75rem;'>";
            html += "<thead><tr style='background:#FFFFFF;'>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; width:60px; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>ROW</th>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; width:80px; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>STATUS</th>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>ERROR</th>";
            html += "</tr></thead><tbody>";

            for (var j = 0; j < file.errors.length; j++) {
                var err = file.errors[j];
                var rowBg = j % 2 === 1 ? "background:rgba(234,237,238,0.3);" : "";
                html += "<tr style='" + rowBg + "'>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#505050;'>" + err.row + "</td>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#D00A6C; font-weight:700;'>FAILED</td>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#222222;'>" + escapeHtml(err.reason) + "</td>";
                html += "</tr>";
            }

            html += "</tbody></table>";
            html += "</div>";
        }

        var passed = file.successful;
        var created = file.created || (file.successful - (file.updated || 0));
        var updated = file.updated || 0;
        var failed = file.failed;
        var total = file.rows;
        html += "<div style='padding:0.5rem 0.75rem; font-family:monospace; font-size:0.75rem; border:1px solid #BBBBBB; border-radius:0 0 4px 4px; background:#FFFFFF;'>";
        html += "Result: <span style='color:#00B24E; font-weight:700;'>" + passed + " passed</span>, ";
        html += "<span style='color:#006798; font-weight:700;'>" + created + " created</span>, ";
        html += "<span style='color:#0082BF; font-weight:700;'>" + updated + " updated</span>, ";
        html += "<span style='color:" + (failed > 0 ? "#D00A6C" : "#222222") + "; font-weight:700;'>" + failed + " failed</span>";
        html += " (" + total + " total)";
        html += "</div>";

        if (file.invalidFile) {
            var sep = downloadBaseUrl.indexOf("?") === -1 ? "?" : "&";
            var url = downloadBaseUrl + sep + "uuid=" + encodeURIComponent(uuid) + "&filename=" + encodeURIComponent(file.invalidFile);
            html += "<div style='margin-top:0.25rem; padding:0.25rem 0.75rem;'>";
            html += "<a href='" + url + "' target='_blank' rel='noopener noreferrer' style='color:#006798; font-size:0.875rem; text-decoration:none;'>" + labels.invalidFiles + ": " + escapeHtml(file.invalidFile) + "</a>";
            html += "</div>";
        }

        return html;
    }

    function escapeHtml(str) {
        var div = document.createElement("div");
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function callCleanup(uuid) {
        if (!uuid || !pluginConfig || !pluginConfig.cleanupUrl) {
            return;
        }

        $.ajax({
            method: "POST",
            url: pluginConfig.cleanupUrl,
            headers: { "X-Csrf-Token": pkp.currentUser.csrfToken },
            data: { uuid: uuid }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
