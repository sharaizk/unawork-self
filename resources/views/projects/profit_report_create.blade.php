<form class="" method="post" enctype="multipart/form-data" action="{{ route('projects.profit.report.store',[$currentWorkspace->slug,$project->id]) }}">
    @csrf
    <div class="modal-body">
        <div class="row">
            <div class="form-group col-md-12">
                <label class="col-form-label">{{ __('Project Name') }}</label>
                <input type="text" class="form-control" id="project_name" placeholder="{{ __('Project Name') }}"
                    name="project_name" value="{{ $project->name }}" disabled>
            </div>

            <div class="form-group col-md-6">
                <label class="col-form-label">{{ __('Profit Name') }}</label>
                <input type="text" class="form-control" id="profit_name" placeholder="{{ __('Enter Title') }}"
                    name="profit_name" required>
            </div>
            <div class="form-group col-md-6">
                <label class="col-form-label">{{ __('Amount') }}</label>
                <input type="text" class="form-control" id="amount" placeholder="{{ __('Enter Amount') }}"
                    name="amount" required>
            </div>
            <div class="form-group col-md-6">
                <label class="form-label">{{ __('Select Date') }}</label>
                <div class="input-group date ">
                    <input class="form-control datepicker2" type="text" id="date" name="date"
                        value="{{ now() }}" autocomplete="off" required>
                    <span class="input-group-text">
                        <i class="feather icon-calendar"></i>
                    </span>
                </div>
            </div>

            <div class="form-group col-md-6">
                <label class="form-label" for="attachment">Attachment</label>
                <input type="file" name="attachment" class="form-control">
            </div>

            <div class="form-group col-md-12 mb-0">
                <label for="task-description" class="col-form-label">{{ __('Description') }}</label>
                <textarea rows="3" class="form-control" id="description" name="description"></textarea>
            </div>
        </div>
    </div>
    <div class=" modal-footer">
        <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
        <input type="submit" value="{{ __('Save Changes') }}" class="btn  btn-primary">
    </div>

</form>


<script>
    (function() {
        const d_week = new Datepicker(document.querySelector('.datepicker2'), {
            buttonClass: 'btn',
            todayBtn: true,
            clearBtn: true,
            format: 'yyyy-mm-dd',
        });
    })();
</script>

<script>
    (function() {
        const d_week = new Datepicker(document.querySelector('.datepicker3'), {
            buttonClass: 'btn',
            todayBtn: true,
            clearBtn: true,
            format: 'yyyy-mm-dd',
        });
    })();
</script>
