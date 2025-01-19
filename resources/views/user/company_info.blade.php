<div class="modal-body">
    <div class="row">
        <div class="row justify-content-center">
            <div class="col-sm-12 col-md-10 col-xxl-12 col-md-12">

                    @php
                        $users = \App\Models\User::where('created_by', $id)->where('type','!=','client')->get();
                    @endphp
                    <div class="card">
                        <div class="card-body">
                            <div class="row workspace">
                                <div class="col-4 text-center">
                                    <p class="mb-0 font-bold">
                                        {{ __('Total User') }}
                                    </p>
                                    <p class="text-sm mb-0" data-toggle="tooltip"
                                        data-bs-original-title="{{ __('Total Users') }}"><i
                                            class="ti ti-users text-warning card-icon-text-space fs-5 mx-1"></i><span
                                            class="total_users fs-5">{{ $userData['total_users'] }}</span>
                                    </p>
                                </div>
                                <div class="col-4 text-center">
                                    <p class="mb-0 font-bold">
                                        {{ __('Active User') }}
                                    </p>
                                    <p class="text-sm mb-0" data-toggle="tooltip"
                                        data-bs-original-title="{{ __('Active Users') }}"><i
                                            class="ti ti-users text-primary card-icon-text-space fs-5 mx-1"></i><span
                                            class="active_users fs-5">{{ $userData['active_users'] }}</span>
                                    </p>
                                </div>
                                <div class="col-4 text-center">
                                    <p class="mb-0 font-bold">
                                        {{ __('Disable User') }}
                                    </p>
                                    <p class="text-sm mb-0" data-toggle="tooltip"
                                        data-bs-original-title="{{ __('Disable Users') }}"><i
                                            class="ti ti-users text-danger card-icon-text-space fs-5 mx-1"></i><span
                                            class="disable_users fs-5">{{ $userData['disable_users'] }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row my-2 ">
                        @if(count($users) > 0)
                        @foreach ($users as $user)
                            <div class="col-md-6 my-2 ">
                                <div class="d-flex align-items-center justify-content-between list_colume_notifi pb-2">
                                    <div class="mb-3 mb-sm-0">
                                        <h6>
                                            <img src="{{ !empty($user->avatar) ? asset(Storage::url('uploads/avatar/' . $user->avatar)) : asset(Storage::url('uploads/avatar/avatar.png')) }}"
                                                class=" wid-40 rounded border-2 border border-primary mx-2" alt="image" height="40">
                                            <label for="user" class="form-label">{{ $user->name }}</label>
                                        </h6>
                                    </div>
                                    <div class="text-end ">
                                        <div class="form-check form-switch custom-switch-v1 mb-2">
                                            <input type="checkbox" name="user_disable"
                                                class="form-check-input input-primary is_disable" value="1"
                                                data-id='{{ $user->id }}' data-company="{{ $id }}"
                                                data-name="{{ __('user') }}"
                                                {{ $user->is_disable == 1 ? 'checked' : '' }}>
                                            <label class="form-check-label" for="user_disable"></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @else
                        <tr>
                            <td colspan="10" class="text-center">
                                <div class="text-center">
                                    <i class="fas fa-folder-open text-primary fs-40"></i>
                                    <h2>{{ __('Opps...') }}</h2>
                                    <h6> {!! __('No User Found') !!} </h6>
                                </div>
                            </td>
                        </tr>
                        @endif
                    </div>
                    {{-- </div> --}}
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).on("click", ".is_disable", function() {
        var status = {{ $status }};
        if(status == 0)
        {
            event.preventDefault();
            show_toastr('error', 'This operation is not perform beacause company is deleted.');
            return false;
        }
        var id = $(this).attr('data-id');
        var company_id = $(this).attr('data-company');
        var is_disable = ($(this).is(':checked')) ? $(this).val() : 0;

        $.ajax({
            url: '{{ route('user.unable') }}',
            type: 'POST',
            data: {
                "is_disable": is_disable,
                "id": id,
                "company_id": company_id,
                "_token": "{{ csrf_token() }}",
            },
            success: function(data) {
                if (data.success) {
                    $('.total_users').text(data.userData.total_users);
                    $('.active_users').text(data.userData.active_users);
                    $('.disable_users').text(data.userData.disable_users);
                    show_toastr('success', data.success);
                } else {
                    show_toastr('error', data.error);

                }

            }
        });
    });
</script>
