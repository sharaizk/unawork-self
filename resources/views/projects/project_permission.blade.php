<div class="modal-body">
    <table class="table mb-0">
        <thead>
            <tr>
                <th>{{__('Module')}}</th>
                <th>{{__('Create')}}</th>
                <th>{{__('Edit')}}</th>
                <th>{{__('Delete')}}</th>
                <th>{{__('Show')}}</th>
                <th>{{__('Move')}}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{__('Milestone')}}</td>
                <td>
                    <input class="form-check-input" id="permission3" @if(in_array('create milestone',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="create milestone">
                </td>
                <td>
                    <input class="form-check-input" id="permission4" @if(in_array('edit milestone',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="edit milestone">
                </td>
                <td>
                    <input class="form-check-input" id="permission5" @if(in_array('delete milestone',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="delete milestone">
                </td>
                <td>
                    <input class="form-check-input" id="permission2" @if(in_array('show milestone',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show milestone">
                </td>
                <td></td> <!-- Empty if no "Move" permission -->
            </tr>
            <tr>
                <td>{{__('Task')}}</td>
                <td>
                    <input class="form-check-input" id="permission7" @if(in_array('create task',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="create task">
                </td>
                <td>
                    <input class="form-check-input" id="permission8" @if(in_array('edit task',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="edit task">
                </td>
                <td>
                    <input class="form-check-input" id="permission9" @if(in_array('delete task',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="delete task">
                </td>
                <td>
                    <input class="form-check-input" id="permission6" @if(in_array('show task',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show task">
                </td>
                <td>
                    <input class="form-check-input" id="permission10" @if(in_array('move task',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="move task">
                </td>
            </tr>
            <tr>
                <td>{{__('Bug Report')}}</td>
                <td>
                    <input class="form-check-input" id="permission17" @if(in_array('create bug report',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="create bug report">
                </td>
                <td>
                    <input class="form-check-input" id="permission18" @if(in_array('edit bug report',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="edit bug report">
                </td>
                <td>
                    <input class="form-check-input" id="permission19" @if(in_array('delete bug report',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="delete bug report">
                </td>
                <td>
                    <input class="form-check-input" id="permission20" @if(in_array('show bug report',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show bug report">
                </td>
                <td>
                    <input class="form-check-input" id="permission21" @if(in_array('move bug report',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="move bug report">
                </td>
            </tr>
            <tr>
                <td>{{__('Expenses')}}</td>
                <td>
                    <input class="form-check-input" id="permission22" @if(in_array('create expenses',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="create expenses">
                </td>
                <td>
                    <input class="form-check-input" id="permission23" @if(in_array('edit expenses',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="edit expenses">
                </td>
                <td>
                    <input class="form-check-input" id="permission24" @if(in_array('delete expenses',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="delete expenses">
                </td>
                <td>
                    <input class="form-check-input" id="permission25" @if(in_array('show expenses',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show expenses">
                </td>
                <td></td>
            </tr>
            <tr>
                <td>{{__('Activity')}}</td>
                <td colspan="5">
                    <input class="form-check-input" id="permission26" @if(in_array('show activity',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show activity">
                </td>
            </tr>
            <tr>
                <td>{{__('Time Sheet')}}</td>
                <td colspan="5">
                    <input class="form-check-input" id="permission27" @if(in_array('show timesheet',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show timesheet">
                </td>
            </tr>
            <tr>
                <td>{{__('Gantt Chart')}}</td>
                <td colspan="5">
                    <input class="form-check-input" id="permission28" @if(in_array('show gantt',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show gantt">
                </td>
            </tr>
            <tr>
                <td>{{__('Uploading')}}</td>
                <td colspan="5">
                    <input class="form-check-input" id="permission29" @if(in_array('show uploading',$permissions)) checked="checked" @endif name="permissions[]" type="checkbox" value="show uploading">
                </td>
            </tr>
        </tbody>
    </table>
</div>