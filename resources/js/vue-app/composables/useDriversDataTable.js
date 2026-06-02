import { crudActionButtonHtml, crudActionsRowHtml } from '../utils/crudActionButtonHtml';

function mapDriverRow(driver, canManage) {
    const mobile = driver.phone ?? '';
    const license = driver.license_number ?? '-';
    const address = driver.address?.trim() ? driver.address : '-';

    let actions = '';

    if (canManage) {
        const editButton = crudActionButtonHtml({
            action: 'edit',
            className: 'edit-driver-btn',
            data: {
                'driver-id': driver.id,
                'driver-name': driver.name ?? '',
                'driver-mobile': mobile,
                'driver-license': driver.license_number ?? '',
                'driver-address': driver.address ?? '',
                'driver-daily-wage': String(driver.daily_wage ?? '0'),
                'driver-is-active': driver.is_active ? '1' : '0',
            },
        });

        const deleteButton = crudActionButtonHtml({
            action: 'delete',
            className: 'delete-driver-btn',
            data: {
                'driver-id': driver.id,
                'driver-name': driver.name ?? '',
            },
        });

        actions = crudActionsRowHtml(`${editButton}${deleteButton}`);
    }

    return {
        name: driver.name ?? '',
        mobile: mobile || '-',
        license_number: license || '-',
        address,
        actions,
    };
}

export function initDriversDataTable({ tableElement, drivers, canManage }) {
    const $ = window.jQuery;

    if (!$ || !$.fn.DataTable) {
        throw new Error('DataTables is not loaded.');
    }

    const rows = drivers.map((driver) => mapDriverRow(driver, canManage));

    const columns = [
        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center',
            width: '80px',
            render(_data, _type, _row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'name', name: 'name', orderable: true, searchable: true },
        { data: 'mobile', name: 'mobile', orderable: true, searchable: true },
        { data: 'license_number', name: 'license_number', orderable: true, searchable: true },
        { data: 'address', name: 'address', orderable: true, searchable: true },
    ];

    if (canManage) {
        columns.push({
            data: 'actions',
            name: 'actions',
            orderable: false,
            searchable: false,
            width: '120px',
            className: 'text-center',
        });
    }

    const table = $(tableElement).DataTable({
        data: rows,
        columns,
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100],
        ],
        dom:
            '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
            '<"row"<"col-sm-12 col-md-12"B>>' +
            '<"row"<"col-sm-12"tr>>' +
            '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: [
            {
                extend: 'excel',
                className: 'btn btn-secondary btn-sm',
                text: '<i class="bi bi-file-earmark-excel"></i> Excel',
            },
        ],
        language: {
            search: 'Search:',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            infoFiltered: '(filtered from _MAX_ total entries)',
            zeroRecords: 'No matching records found',
            emptyTable: 'No data available in table',
            paginate: {
                first: 'First',
                last: 'Last',
                next: 'Next',
                previous: 'Previous',
            },
        },
        responsive: true,
        autoWidth: false,
        searchDelay: 500,
        initComplete() {
            this.api()
                .columns([1, 2, 3, 4])
                .every(function initColumnSearch() {
                    const column = this;
                    const $header = $(column.header());
                    const title = $header.text().trim();

                    $header.html(`<div style="margin-bottom: 5px;">${title}</div>`);

                    let debounceTimer;

                    $('<input type="text" class="form-control form-control-sm" />')
                        .attr('placeholder', `Search ${title}`)
                        .appendTo($header)
                        .on('keyup', function onKeyup() {
                            clearTimeout(debounceTimer);
                            const value = this.value;

                            debounceTimer = setTimeout(() => {
                                if (column.search() !== value) {
                                    column.search(value).draw();
                                }
                            }, 500);
                        });
                });
        },
    });

    return table;
}

export function reloadDriversDataTable(table, drivers, canManage) {
    const rows = drivers.map((driver) => mapDriverRow(driver, canManage));
    table.clear();
    table.rows.add(rows);
    table.draw(false);
}
