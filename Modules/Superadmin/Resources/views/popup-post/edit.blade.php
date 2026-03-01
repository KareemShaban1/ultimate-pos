@extends('layouts.app')
@section('title', __('superadmin::lang.superadmin') . ' | Business')

@section('content')
@include('superadmin::layouts.nav')
<div class="box box-solid">
    <div class="box-header">
        <h3 class="box-title">@lang( 'superadmin::lang.add_new_business' ) <small>(@lang( 'superadmin::lang.add_business_help' ))</small></h3>
    </div>

    <div class="box-body">
        {!! Form::open(['url' => action('\Modules\Superadmin\Http\Controllers\SuperadminSettingsController@updatePopupPost'), 'method' => 'patch', 'id' => 'popub_update_form','files' => true ]) !!}
            <div class="col-sm-12">
                <div class="form-group">
                {!! Form::label('popup_post', __('lang_v1.post_popup') . ':' ) !!}
                    {!! Form::textarea('popup_post',$post_popup->popup_post, ['class' => 'form-control',
                    'placeholder' => __('lang_v1.post_popup'), 'rows' => 3,'id'=>'post_popup']); !!}
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <div class="checkbox">
                        <label>
                        {!! Form::checkbox('popup_disable', 1,  $post_popup->popup_disable , 
                        [ 'class' => 'input-icheck']); !!} {{ __( 'lang_v1.disable_post_popup' ) }}
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <label>{{ __( 'lang_v1.time_post_popup' ) }}</label>
                    {!! Form::number('popup_time',$post_popup->popup_time , 
                    [ 'class' => 'form-control']); !!} 
                </div>
            </div>
            <div class="row">
                <div class="col-xs-12">
                    <div class="form-group pull-right">
                    {{Form::submit(__('messages.update'), ['class'=>"btn btn-danger"])}}
                    </div>
                </div>
            </div>
        {!! Form::close() !!}
    </div>
  </div>

  @endsection
  