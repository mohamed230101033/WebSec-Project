@extends('layouts.master')
@section('title', isset($isReadOnly) && $isReadOnly ? 'View User' : 'Edit User')
@section('content')
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
$(document).ready(function(){
  $("#clean_permissions").click(function(){
    $('#permissions').val([]);
  });
  $("#clean_roles").click(function(){
    $('#roles').val([]);
  });
});
</script>
<div class="d-flex justify-content-center">
    <div class="row m-4 col-sm-8">
        @if(isset($isReadOnly) && $isReadOnly)
        <div class="alert alert-info">
            <strong>Note:</strong> As an Editor, you can view user details but cannot edit them.
        </div>
        @endif
        
        <form action="{{route('users_save', $user->id)}}" method="post">
            {{ csrf_field() }}
            @foreach($errors->all() as $error)
            <div class="alert alert-danger">
            <strong>Error!</strong> {{$error}}
            </div>
            @endforeach
            <div class="row mb-2">
                <div class="col-12">
                    <label for="code" class="form-label">Name:</label>
                    <input type="text" class="form-control" placeholder="Name" name="name" required value="{{$user->name}}" 
                           {{ isset($isReadOnly) && $isReadOnly ? 'disabled' : '' }}>
                </div>
            </div>
            
            @if(auth()->user()->hasPermissionTo('admin_users') || (isset($isReadOnly) && $isReadOnly))
            <div class="col-12 mb-3">
                <label for="roles" class="form-label">Roles: 
                    @if(!isset($isReadOnly) || !$isReadOnly)
                        <small class="text-muted">(<a href='#' id='clean_roles'>reset</a>)</small>
                    @endif
                </label>
                <div class="card">
                    <div class="card-body p-2">
                        <select multiple class="form-select" id='roles' name="roles[]" style="min-height: 150px;" 
                                {{ isset($isReadOnly) && $isReadOnly ? 'disabled' : '' }}>
                            @foreach($roles as $role)
                            <option value='{{$role->name}}' {{$role->taken ? 'selected' : ''}}>
                                {{$role->name}}
                            </option>
                            @endforeach
                        </select>
                        <div class="mt-2">
                            @if(auth()->user()->hasRole('Editor'))
                            <a href="{{ route('roles.index') }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-gear"></i> Manage Roles
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 mb-3">
                <label for="permissions" class="form-label">Direct Permissions: 
                    @if(!isset($isReadOnly) || !$isReadOnly)
                        <small class="text-muted">(<a href='#' id='clean_permissions'>reset</a>)</small>
                    @endif
                </label>
                <div class="card">
                    <div class="card-body p-2">
                        <select multiple class="form-select" id='permissions' name="permissions[]" style="min-height: 150px;" 
                                {{ isset($isReadOnly) && $isReadOnly ? 'disabled' : '' }}>
                        @foreach($permissions as $permission)
                            <option value='{{$permission->name}}' {{$permission->taken ? 'selected' : ''}}>
                                {{$permission->name}}
                            </option>
                            @endforeach
                        </select>
                        <div class="form-text small mt-1">
                            <i class="bi bi-info-circle"></i> Direct permissions override role-based permissions.
                        </div>
                    </div>
                </div>
            </div>
            @endif
            
            <div class="d-flex justify-content-between mt-3">
                <a href="{{ route('users') }}" class="btn btn-secondary">Back to Users</a>
                @if(!isset($isReadOnly) || !$isReadOnly)
                <button type="submit" class="btn btn-primary">Save Changes</button>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
