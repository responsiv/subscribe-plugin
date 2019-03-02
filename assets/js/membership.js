function editSchedulePriceSelected() {
    var checked = $('#scheduleList').listWidget('getChecked')

    if (!checked.length) {
        $.oc.alert('Please select schedules to edit.');
        return false
    }

    $.popup('onLoadSchedulePriceForm', {
        extraData: { list_ids: checked }
    })

    return false
}

function editSchedulePrice(period) {
    if (!period) {
        $.oc.alert('Please select schedules to edit.')
        return false
    }

    $.popup('onLoadSchedulePriceForm', {
        extraData: { list_id: period }
    })

    return false
}
