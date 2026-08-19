(function (window, document) {
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(value);
        return div.innerHTML;
    }

    function normalizeValue(value) {
        return String(value === null || value === undefined ? '' : value).trim();
    }

    function createMessageRow(colspan, message, className) {
        const safeMessage = escapeHtml(message || 'No data');
        return `
            <tr>
                <td colspan="${colspan}" class="text-center py-4 ${className || 'text-muted'}">
                    ${safeMessage}
                </td>
            </tr>
        `;
    }

    function createPaginationItem(label, page, disabled, active, onClick) {
        const li = document.createElement('li');
        li.className = 'page-item';

        if (disabled) li.classList.add('disabled');
        if (active) li.classList.add('active');

        const anchor = document.createElement('a');
        anchor.className = 'page-link';
        anchor.href = 'javascript:void(0)';
        anchor.textContent = label;

        if (!disabled && !active) {
            anchor.addEventListener('click', function () {
                onClick(page);
            });
        }

        li.appendChild(anchor);
        return li;
    }

    function createClientTableManager(config) {
        if (!config || !config.tbodyId) {
            throw new Error('createClientTableManager requires a tbodyId');
        }

        const state = {
            allData: [],
            filteredData: [],
            currentPage: 1
        };

        const filters = Array.isArray(config.filters) ? config.filters : [];

        function getElement(id) {
            return id ? document.getElementById(id) : null;
        }

        function getPerPage() {
            const perPageElement = getElement(config.perPageId);
            const value = parseInt(perPageElement && perPageElement.value, 10);
            return Number.isFinite(value) && value > 0 ? value : 10;
        }

        function getFilterValue(filter) {
            const element = getElement(filter.id);
            return normalizeValue(element ? element.value : '').toLowerCase();
        }

        function getItemValue(item, filter) {
            if (typeof filter.getValue === 'function') {
                return normalizeValue(filter.getValue(item));
            }

            if (filter.key) {
                return normalizeValue(item[filter.key]);
            }

            return '';
        }

        function populateSelectOptions() {
            filters.forEach(function (filter) {
                if (filter.type !== 'select' || filter.noPopulate || !filter.id) return;

                const select = getElement(filter.id);
                if (!select) return;

                const currentValue = select.value;
                const values = Array.from(
                    new Set(
                        state.allData
                            .map(function (item) {
                                return getItemValue(item, filter);
                            })
                            .filter(Boolean)
                    )
                ).sort(function (a, b) {
                    return a.localeCompare(b);
                });

                const defaultLabel = filter.defaultLabel || 'All';
                select.innerHTML = `<option value="">${escapeHtml(defaultLabel)}</option>`;

                values.forEach(function (value) {
                    select.innerHTML += `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`;
                });

                if (values.indexOf(currentValue) !== -1) {
                    select.value = currentValue;
                }
            });
        }

        function applyCurrentFilters() {
            state.filteredData = state.allData.filter(function (item) {
                return filters.every(function (filter) {
                    if (!filter.id) return true;

                    const filterValue = getFilterValue(filter);
                    if (!filterValue) return true;

                    const itemValue = getItemValue(item, filter).toLowerCase();
                    if (filter.type === 'select') {
                        return itemValue === filterValue;
                    }

                    return itemValue.indexOf(filterValue) !== -1;
                });
            });

            return state.filteredData;
        }

        function updateShowingInfo(totalCount, pageRowsLength) {
            const element = getElement(config.showingInfoId);
            if (!element) return;

            if (!totalCount || !pageRowsLength) {
                element.textContent = 'Showing 0 entries';
                return;
            }

            const perPage = getPerPage();
            const from = ((state.currentPage - 1) * perPage) + 1;
            const to = from + pageRowsLength - 1;
            element.textContent = `Showing ${from} to ${to} of ${totalCount} entries`;
        }

        function renderPagination(totalPages) {
            const pagination = getElement(config.paginationId);
            if (!pagination) return;

            pagination.innerHTML = '';

            if (!totalPages || totalPages <= 1) return;

            pagination.appendChild(
                createPaginationItem('‹', state.currentPage - 1, state.currentPage === 1, false, goToPage)
            );

            for (let page = 1; page <= totalPages; page += 1) {
                if (totalPages > 7 && page > 3 && page < totalPages - 1 && Math.abs(page - state.currentPage) > 1) {
                    if (page === 4 || page === totalPages - 2) {
                        const dots = document.createElement('li');
                        dots.className = 'page-item disabled';
                        dots.innerHTML = '<span class="page-link">...</span>';
                        pagination.appendChild(dots);
                    }
                    continue;
                }

                pagination.appendChild(
                    createPaginationItem(String(page), page, false, page === state.currentPage, goToPage)
                );
            }

            pagination.appendChild(
                createPaginationItem('›', state.currentPage + 1, state.currentPage === totalPages, false, goToPage)
            );
        }

        function renderRows() {
            const tbody = getElement(config.tbodyId);
            if (!tbody) return;

            populateSelectOptions();

            const filteredData = applyCurrentFilters();
            const perPage = getPerPage();
            const totalPages = filteredData.length ? Math.ceil(filteredData.length / perPage) : 0;

            if (state.currentPage > totalPages && totalPages > 0) {
                state.currentPage = totalPages;
            }

            const startIndex = (state.currentPage - 1) * perPage;
            const pageRows = filteredData.slice(startIndex, startIndex + perPage);

            if (!filteredData.length) {
                tbody.innerHTML = createMessageRow(
                    config.colspan || 1,
                    config.noDataMessage || 'No data found',
                    config.noDataClassName || 'text-muted'
                );
                updateShowingInfo(0, 0);
                renderPagination(0);
                if (typeof config.afterRender === 'function') {
                    config.afterRender([]);
                }
                return;
            }

            tbody.innerHTML = pageRows.map(function (item, index) {
                return config.renderRow(item, {
                    rowNumber: startIndex + index + 1,
                    index: startIndex + index
                });
            }).join('');

            updateShowingInfo(filteredData.length, pageRows.length);
            renderPagination(totalPages);

            if (typeof config.afterRender === 'function') {
                config.afterRender(pageRows);
            }
        }

        function setData(data) {
            state.allData = Array.isArray(data) ? data.slice() : [];
            state.currentPage = 1;
            renderRows();
        }

        function setMessage(message, className) {
            const tbody = getElement(config.tbodyId);
            if (!tbody) return;

            state.allData = [];
            state.filteredData = [];
            state.currentPage = 1;
            tbody.innerHTML = createMessageRow(config.colspan || 1, message, className);
            updateShowingInfo(0, 0);
            renderPagination(0);
        }

        function setLoading(message) {
            setMessage(message || 'Loading...', 'text-muted');
        }

        function setError(message) {
            setMessage(message || 'Failed to load data', 'text-danger');
        }

        function goToPage(page) {
            const nextPage = parseInt(page, 10);
            if (!Number.isFinite(nextPage) || nextPage < 1) return;
            state.currentPage = nextPage;
            renderRows();
        }

        function bindEvents() {
            filters.forEach(function (filter) {
                if (!filter.id) return;
                const element = getElement(filter.id);
                if (!element) return;

                const eventName = filter.type === 'select' ? 'change' : 'input';
                element.addEventListener(eventName, function () {
                    state.currentPage = 1;
                    renderRows();
                });
            });

            const perPageElement = getElement(config.perPageId);
            if (perPageElement) {
                perPageElement.addEventListener('change', function () {
                    state.currentPage = 1;
                    renderRows();
                });
            }
        }

        bindEvents();

        return {
            setData: setData,
            setLoading: setLoading,
            setError: setError,
            setMessage: setMessage,
            render: renderRows,
            goToPage: goToPage,
            state: state,
            escapeHtml: escapeHtml,
            normalizeValue: normalizeValue
        };
    }

    window.TableCardHelper = {
        createClientTableManager: createClientTableManager,
        escapeHtml: escapeHtml,
        normalizeValue: normalizeValue
    };
})(window, document);
