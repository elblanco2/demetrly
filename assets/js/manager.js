/**
 * Subdomain Manager JavaScript
 * Handles tab switching, listing, deletion, and modals
 */

(function() {
    'use strict';

    // State
    let currentPage = 0;
    let currentStatus = 'active';
    const pageSize = 20;
    let csrfToken = null;

    // HTML Escape Function (XSS Protection)
    function escapeHtml(text) {
        if (!text) return text;
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // DOM Elements
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    const refreshBtn = document.getElementById('refresh-btn');
    const exportJsonBtn = document.getElementById('export-json-btn');
    const exportCsvBtn = document.getElementById('export-csv-btn');
    const subdomainListBody = document.getElementById('subdomain-list');
    const statusFilter = document.getElementById('status-filter');

    // Modals
    const deleteModal = document.getElementById('delete-modal');
    const logsModal = document.getElementById('logs-modal');
    const deleteConfirmInput = document.getElementById('delete-confirm-input');
    const confirmDeleteBtn = document.getElementById('confirm-delete');
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    const closeLogsBtn = document.getElementById('close-logs');

    // Current deletion target
    let currentDeletionTarget = null;

    // Initialize
    function init() {
        // Get CSRF token from meta tag or form
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            csrfToken = csrfMeta.content;
        } else {
            // Try to get from hidden input in form
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                csrfToken = csrfInput.value;
            }
        }

        setupTabs();
        setupEventListeners();
        loadSubdomains();
    }

    // Tab Switching
    function setupTabs() {
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabName = button.getAttribute('data-tab');

                // Update buttons
                tabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                // Update content
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === `${tabName}-tab`) {
                        content.classList.add('active');
                    }
                });

                // Load data if switching to manage tab
                if (tabName === 'manage') {
                    loadSubdomains();
                }
            });
        });
    }

    // Event Listeners
    function setupEventListeners() {
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadSubdomains());
        }

        if (exportJsonBtn) {
            exportJsonBtn.addEventListener('click', () => exportData('json'));
        }

        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => exportData('csv'));
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                currentStatus = e.target.value;
                currentPage = 0;
                loadSubdomains();
            });
        }

        // Delete modal
        if (deleteConfirmInput) {
            deleteConfirmInput.addEventListener('input', (e) => {
                if (currentDeletionTarget) {
                    confirmDeleteBtn.disabled = e.target.value !== currentDeletionTarget.name;
                }
            });
        }

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', executeDeletion);
        }

        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', closeDeleteModal);
        }

        if (closeLogsBtn) {
            closeLogsBtn.addEventListener('click', closeLogsModal);
        }

        // Close modals on background click
        if (deleteModal) {
            deleteModal.addEventListener('click', (e) => {
                if (e.target === deleteModal) {
                    closeDeleteModal();
                }
            });
        }

        if (logsModal) {
            logsModal.addEventListener('click', (e) => {
                if (e.target === logsModal) {
                    closeLogsModal();
                }
            });
        }
    }

    // Load Subdomains
    async function loadSubdomains() {
        if (!subdomainListBody) return;

        // Show loading
        subdomainListBody.innerHTML = `
            <tr>
                <td colspan="6" class="loading">
                    <div class="spinner"></div>
                    Loading subdomains...
                </td>
            </tr>
        `;

        try {
            const response = await fetch(`ajax/list_subdomains.php?status=${currentStatus}&limit=${pageSize}&offset=${currentPage * pageSize}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load subdomains');
            }

            renderSubdomains(result.data);
            renderPagination(result);
        } catch (error) {
            subdomainListBody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state">
                        <p style="color: #f44336;">Error: ${error.message}</p>
                    </td>
                </tr>
            `;
        }
    }

    // Render Subdomains
    function renderSubdomains(subdomains) {
        if (subdomains.length === 0) {
            subdomainListBody.innerHTML = `
                <tr>
                    <td colspan="6" class="empty-state">
                        <p>No subdomains found</p>
                    </td>
                </tr>
            `;
            return;
        }

        subdomainListBody.innerHTML = subdomains.map(sub => `
            <tr>
                <td>
                    <a href="https://${escapeHtml(sub.full_domain)}" target="_blank" class="subdomain-link">
                        ${escapeHtml(sub.subdomain_name)}
                    </a>
                </td>
                <td>${escapeHtml(sub.educational_focus) || '-'}</td>
                <td>${escapeHtml(sub.primary_lms) || 'none'}</td>
                <td>${escapeHtml(sub.created_at_formatted)}</td>
                <td>${escapeHtml(sub.ai_generated_text)}</td>
                <td>
                    <span class="status-badge status-${escapeHtml(sub.status)}">${escapeHtml(sub.status)}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-small btn-view" onclick="viewLogs(${parseInt(sub.id)})">
                            Logs
                        </button>
                        ${sub.status === 'active' ? `
                            <button class="btn-small btn-delete" onclick="initiateDeletion(${parseInt(sub.id)}, '${escapeHtml(sub.subdomain_name)}', '${escapeHtml(sub.database_name)}', '${escapeHtml(sub.directory_path)}', '${escapeHtml(sub.full_domain)}')">
                                Delete
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Render Pagination
    function renderPagination(result) {
        const paginationDiv = document.getElementById('pagination');
        if (!paginationDiv) return;

        const start = result.offset + 1;
        const end = Math.min(result.offset + result.limit, result.total);

        paginationDiv.innerHTML = `
            <div class="pagination-info">
                Showing ${start}-${end} of ${result.total}
            </div>
            <div class="pagination-buttons">
                <button ${currentPage === 0 ? 'disabled' : ''} onclick="previousPage()">
                    Previous
                </button>
                <button ${!result.hasMore ? 'disabled' : ''} onclick="nextPage()">
                    Next
                </button>
            </div>
        `;
    }

    // Pagination
    window.previousPage = function() {
        if (currentPage > 0) {
            currentPage--;
            loadSubdomains();
        }
    };

    window.nextPage = function() {
        currentPage++;
        loadSubdomains();
    };

    // Initiate Deletion
    window.initiateDeletion = function(id, name, database, directory, full_domain) {
        currentDeletionTarget = { id, name, database, directory, full_domain };

        // Use full_domain if provided, otherwise use name
        const displayDomain = full_domain || name;
        document.getElementById('modal-subdomain').textContent = displayDomain;
        document.getElementById('modal-database').textContent = database;
        document.getElementById('modal-directory').textContent = directory;

        deleteConfirmInput.value = '';
        confirmDeleteBtn.disabled = true;

        deleteModal.classList.add('active');
    };

    // Execute Deletion
    async function executeDeletion() {
        if (!currentDeletionTarget || !csrfToken) return;

        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.textContent = 'Deleting...';

        try {
            const response = await fetch('ajax/delete_subdomain.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subdomain_id: currentDeletionTarget.id,
                    confirm_name: deleteConfirmInput.value,
                    csrf_token: csrfToken
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('Subdomain deleted successfully!');
                closeDeleteModal();
                loadSubdomains();
            } else {
                if (result.errors && result.errors.length > 0) {
                    alert('Deletion partially failed:\\n\\n' + result.errors.join('\\n'));
                    closeDeleteModal();
                    loadSubdomains();
                } else {
                    alert('Deletion failed: ' + (result.error || 'Unknown error'));
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.textContent = 'Delete';
                }
            }
        } catch (error) {
            alert('Error: ' + error.message);
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.textContent = 'Delete';
        }
    }

    // View Logs
    window.viewLogs = async function(id) {
        const logsContent = document.getElementById('logs-content');
        if (!logsContent) return;

        logsContent.innerHTML = '<div class="loading"><div class="spinner"></div>Loading logs...</div>';
        logsModal.classList.add('active');

        try {
            const response = await fetch(`ajax/get_logs.php?subdomain_id=${id}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load logs');
            }

            renderLogs(result);
        } catch (error) {
            logsContent.innerHTML = `<p style="color: #f44336;">Error: ${error.message}</p>`;
        }
    };

    // Render Logs
    function renderLogs(data) {
        const logsContent = document.getElementById('logs-content');

        let html = `
            <h4>Subdomain: ${data.subdomain.name}</h4>
            <p><strong>Domain:</strong> ${data.subdomain.full_domain}</p>
            <p><strong>Status:</strong> <span class="status-badge status-${data.subdomain.status}">${data.subdomain.status}</span></p>
            <p><strong>Created:</strong> ${data.subdomain.created_at}</p>
        `;

        if (data.creation_logs && data.creation_logs.length > 0) {
            html += '<h4 style="margin-top: 20px;">Creation Logs:</h4>';
            html += data.creation_logs.map(log => `
                <div class="log-entry status-${log.status}">
                    <div class="log-timestamp">${log.timestamp_formatted}</div>
                    <div class="log-step">${log.step_name}</div>
                    ${log.message ? `<div class="log-message">${log.message}</div>` : ''}
                </div>
            `).join('');
        }

        if (data.deletion_log) {
            html += `
                <div class="deletion-info">
                    <h4>Deletion Information</h4>
                    <div class="deletion-detail"><strong>Deleted:</strong> ${data.deletion_log.deleted_at_formatted}</div>
                    <div class="deletion-detail"><strong>Cloudflare DNS:</strong> ${data.deletion_log.cloudflare_deleted ? 'Deleted' : 'Failed'}</div>
                    <div class="deletion-detail"><strong>cPanel Subdomain:</strong> ${data.deletion_log.cpanel_deleted ? 'Deleted' : 'Failed'}</div>
                    <div class="deletion-detail"><strong>Database:</strong> ${data.deletion_log.database_deleted ? 'Deleted' : 'Failed'}</div>
                    <div class="deletion-detail"><strong>Files:</strong> ${data.deletion_log.files_deleted ? 'Deleted' : 'Failed'}</div>
                    ${data.deletion_log.errors_decoded && data.deletion_log.errors_decoded.length > 0 ? `
                        <div class="deletion-detail" style="margin-top: 10px;">
                            <strong>Errors:</strong>
                            <ul style="margin: 5px 0; padding-left: 20px;">
                                ${data.deletion_log.errors_decoded.map(e => `<li>${e}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        logsContent.innerHTML = html;
    }

    // Close Modals
    function closeDeleteModal() {
        deleteModal.classList.remove('active');
        currentDeletionTarget = null;
        deleteConfirmInput.value = '';
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.textContent = 'Delete';
    }

    function closeLogsModal() {
        logsModal.classList.remove('active');
    }

    // Export Data
    function exportData(format) {
        window.location.href = `ajax/export_data.php?format=${format}`;
    }

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
