<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Face Recognition - Get and Transfer Data</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta content="width=device-width, initial-scale=1" name="viewport" />
        <meta name="robots" content="noindex,nofollow">
        <meta content="" name="author" />
        <base href="http://localhost:8080/faceapp/">
        <link rel="shortcut icon" href="{{asset('assets/img/ioi_icon.png')}}" />

        <link href="{{asset('vendor/font-awesome-old/css/font-awesome.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/font-awesome/css/fontawesome.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/font-awesome/css/brands.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/font-awesome/css/solid.css')}}" rel="stylesheet" type="text/css" />                

        <link href="{{asset('vendor/template_assets/global/plugins/bootstrap/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css" />

        <link href="{{asset('vendor/template_assets/global/plugins/datatables/datatables.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/global/plugins/select2/css/select2.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/global/plugins/select2/css/select2-bootstrap.min.css')}}" rel="stylesheet" type="text/css" />

        <link href="{{asset('vendor/template_assets/global/plugins/bootstrap-daterangepicker/daterangepicker.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/global/plugins/bootstrap-timepicker/css/bootstrap-timepicker.min.css')}}" rel="stylesheet" type="text/css" />
        <!--<link href="{{asset('vendor/template_assets/global/plugins/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css')}}"" rel="stylesheet" type="text/css" />-->

        <link href="{{asset('vendor/template_assets/global/css/components-rounded.min.css')}}" rel="stylesheet" id="style_components" type="text/css" />
        <link href="{{asset('vendor/template_assets/global/css/plugins.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/layouts/layout4/css/layout.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('vendor/template_assets/layouts/layout4/css/themes/default.min.css')}}" rel="stylesheet" type="text/css" id="style_color" />
        <link href="{{asset('vendor/template_assets/layouts/layout4/css/custom.min.css')}}" rel="stylesheet" type="text/css" />
        <link rel="shortcut icon" type="image/png" href="{{asset('assets/img/ioi_icon.png')}}"/>
        <style>
            #spinner-div {
                position: fixed;
                display: none;
                width: 100%;
                height: 100%;
                top: 0;
                left: 0;
                text-align: center;
                background-color: rgba(255, 255, 255, 0.8);
                z-index: 2;
            }
            .spinner-border {
                margin-top:25%;
                display: inline-block;
                width: 2rem;
                height: 2rem;
                vertical-align: -.125em;
                border: .25em solid currentColor;
                border-right-color: transparent;
                border-radius: 50%;
                /*                -webkit-animation: .75s linear infinite spinner-border;
                                animation: .75s linear infinite spinner-border;*/

                -webkit-animation-name: drive;
                -webkit-animation-duration: 2s;
                -webkit-animation-timing-function: ease-in;
                -webkit-animation-iteration-count: 1;
            }
            .pt-5 {
                padding-top: 3rem !important;
            }
        </style>        
    </head>
    <body class="page-container-bg-solid page-header-fixed page-sidebar-closed-hide-logo" onafterprint="myFunction()">
        <div class="page-header navbar navbar-fixed-top">
            <div class="page-header-inner ">
                <div class="page-logo">
                    <a href="home">
                        <img src="{{asset('assets/img/logo-w-logo.png')}}" style="height:65px;width:auto; margin:4px 70px;" alt="logo" /> 
                    </a>
                </div>
                <a href="javascript:;" class="menu-toggler responsive-toggler" data-toggle="collapse" data-target=".navbar-collapse"> 
                </a>
                <div class="page-top">
                </div>
            </div>
        </div>
        <div class="clearfix"> </div>
        <div class="page-container" style="background:#EEE;">
            <div class="page-sidebar-wrapper">
                <div class="page-sidebar navbar-collapse collapse">
                    <ul class="page-sidebar-menu" data-keep-expanded="false" data-auto-scroll="true" data-slide-speed="200">
                        <li class="nav-item ">
                            <a href="{{ route('webreport') }}" class="nav-link">
                                <i class="fa fa-home"></i>
                                <span class="title">Home</span>
                            </a>
                        </li>
                        <li class="nav-item ">
                            <a href="{{ route('group') }}" class="nav-link">
                                <i class="fa fa-database"></i>
                                <span class="title">Group List</span>
                                <span class="selected"></span>
                                <span class="arrow "></span>
                            </a>
                        </li> 
                        <li class="nav-item ">
                            <a href="{{ route('person') }}" class="nav-link">
                                <i class="fa fa-database"></i>
                                <span class="title">Person List</span>
                                <span class="selected"></span>
                                <span class="arrow "></span>
                            </a>
                        </li>                           
                        <li class="nav-item open">
                            <a href="javascript:;" class="nav-link nav-toggle">
                                <i class="fa fa-chart-bar"></i>
                                <span class="title">Reporting</span>
                                <span class="selected"></span>
                                <span class="arrow "></span>
                            </a>
                            <ul class="sub-menu" style="display: block;">
                                <li class="nav-item ">
                                    <a href="{{ route('setting.index') }}" class="nav-link">
                                        <i class="fa fa-chart-bar"></i>
                                        <span class="title">Setting</span>
                                        <span class="selected"></span>
                                        <span class="arrow "></span>
                                    </a>
                                </li>                               
                                <li>
                                    <a href="{{ route('webreport') }}" class="nav-link">
                                        <i class="fa fa-chart-bar"></i>
                                        <span class="title">Report</span>
                                        <span class="selected"></span>
                                        <span class="arrow "></span>
                                    </a>                                    
                                </li>
                                <li class="nav-item">
                                    <a href="{{ route('weblog') }}" class="nav-link">
                                        <i class="fa fa-chart-bar"></i>
                                        <span class="title">Log</span>
                                        <span class="selected"></span>
                                        <span class="arrow "></span>
                                    </a>                                    
                                </li>                                 
                                <li class="nav-item active">
                                    <a href="{{ route('sendsapindex') }}" class="nav-link">
                                        <i class="fa fa-chart-bar"></i>
                                        <span class="title">Send Data</span>
                                        <span class="selected"></span>
                                        <span class="arrow "></span>
                                    </a>                                    
                                </li>                                
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="page-content-wrapper">
                <div class="page-content">
                    <div class="page-head">
                        <div class="page-title">
                            <h1>Get and Transfer Data Attendance</h1>
                        </div>
                    </div>
                    <ul class="page-breadcrumb breadcrumb">
                        <li>
                            <a href="#">Home</a>
                            <i class="fa fa-circle"></i>
                        </li>
                        <li>
                            <a href="#">Reporting</a>
                            <i class="fa fa-circle"></i>
                        </li>
                        <li>
                            <span class="active">Transfer</span>
                        </li>
                    </ul>

                    <div class="row">
                        <div class="col-md-12">
                        </div>
                        <div class="col-md-12">
                            <div class="portlet light bordered">
                                <div class="portlet-title">
                                    <div class="caption">
                                        <span class="caption-subject font-blue sbold uppercase blue">Transfer</span>
                                    </div>
                                </div>
                                <div class="portlet-body form">
                                    <form class="form-horizontal" role="form" action="{{ route('setting.store') }}" method="POST" enctype="multipart/form-data">                                    
                                        @csrf   
                                        <div class="form-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="col-md-5 control-label"><strong>Date Time Start</strong> </label>
                                                        <div class="col-md-7">
                                                            <input type="text" name="startdate" value="{{$date_start}}" id="startdate" class="form-control" placeholder="Start Date">
                                                            @error('startdate')
                                                            <div class="alert alert-danger mt-1 mb-1">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>                                                  
                                                    <div class="form-group">
                                                        <label class="col-md-5 control-label"><strong>Date Time End</strong> </label>
                                                        <div class="col-md-7">
                                                            <input type="text" name="enddate" value="{{$date_end}}" id="enddate" class="form-control" placeholder="End Date">
                                                        </div>
                                                        @error('enddate')
                                                        <div class="alert alert-danger mt-1 mb-1">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="col-md-5 control-label">&nbsp;</label>
                                                        <div class="col-md-7">
                                                            <table>
                                                                <tbody><tr>
                                                                        <td>
                                                                            <button class="btn btn-primary ml-3" type="button" style="width: 150px;" id="get_dss">Get DSS</button>
                                                                        </td>
                                                                        <td>
                                                                            <button class="btn btn-primary ml-3" type="button" style="margin-left:15px;width: 150px;" id="send_sap">Sent SAP</button>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>                                                            
                                                        </div>
                                                        @error('ip_server_fr')
                                                        <div class="alert alert-danger mt-1 mb-1">{{ $message }}</div>
                                                        @enderror
                                                    </div>                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <!--                                        <div class="form-actions right">
                                                                                    <button type="submit" class="btn btn-outline btn-circle btn-md blue" data-toggle="modal"
                                                                                            data-target="#save_modal"><i class="fa fa-save"></i> Save</button>
                                                                                </div>-->
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="page-footer">
            <div class="page-footer-inner">
                <a> FaceApi IOI V 3.3.0.4 (20231003)</a>
            </div>
            <div class="scroll-to-top">
                <i class="icon-arrow-up"></i>
            </div>
        </div>
        <div id="spinner-div" class="pt-5">
            <div class="spinner-border text-primary" role="status">
            </div>
        </div>          
        <div class="quick-nav-overlay"></div>

        <script src="{{asset('vendor/template_assets/global/plugins/jquery.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/global/plugins/bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/global/plugins/js.cookie.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/global/plugins/jquery.blockui.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/global/plugins/datatables/datatables.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/datatables/js/dataTables.rowGroup.min.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/global/plugins/jquery-repeater/jquery.repeater.js')}}" type='text/javascript'></script><script src="{{asset('vendor/template_assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js')}}" type='text/javascript'></script>

        <script src="{{asset('vendor/template_assets/global/plugins/counterup/jquery.waypoints.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/global/plugins/counterup/jquery.counterup.min.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/global/scripts/app.min.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/pages/scripts/components-date-time-pickers.min.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/global/plugins/select2/js/select2.full.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/pages/scripts/components-select2.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js')}}" type="text/javascript"></script>
        <!--<script src="{{asset('vendor/template_assets/global/plugins/bootstrap-timepicker/js/bootstrap-timepicker.min.js')}}" type="text/javascript"></script>-->
        <!--<script src="{{asset('vendor/template_assets/global/plugins/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>-->
        <script src="{{asset('vendor/template_assets/pages/scripts/components-date-time-pickers.js')}}" type="text/javascript"></script>

        <script src="{{asset('vendor/template_assets/layouts/layout4/scripts/layout.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/layouts/layout4/scripts/demo.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/layouts/global/scripts/quick-sidebar.min.js')}}" type="text/javascript"></script>
        <script src="{{asset('vendor/template_assets/layouts/global/scripts/quick-nav.min.js')}}" type="text/javascript"></script>

        <link type="text/css" href="{{asset('vendor/datatables/js/dataTables.checkboxes.css')}}" rel="stylesheet" />
        <script type="text/javascript" src="{{asset('vendor/datatables/js/dataTables.checkboxes.min.js')}}"></script>
        <script>
function myFunction() {
    location.reload();
}
$(document).ready(function ()
{
    $(document).on('focus', ':input', function () {
        $(this).attr('autocomplete', 'off');
    });
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    $('#startdate').datepicker({format: 'yyyy-mm-dd', autoclose: true, endDate: '0'});
    $('#enddate').datepicker({format: 'yyyy-mm-dd', autoclose: true, endDate: '0'});
    $('#get_dss').click(function () {
        if (this.value !== 'undefined') {
            $.ajax({
                method: "POST",
                url: "sendsap.transfer",
                data: {type: 'pull', date_start: $('#startdate').val(), date_end: $('#enddate').val()},
                dataType: 'json',
                beforeSend: function () {
                    $('#spinner-div').show();
                },
                success: function (msg) {
                    $('#spinner-div').hide();
                    if (msg.status == 'success') {
                        alert("Get data completed. System success to collect data from DSS server.");
                    } else {
                        var txt = 'Transfer Completed.' + msg.message
                        alert(txt)
                    }
                }
            })
        }
    })
    $('#send_sap').click(function () {
        if (this.value !== 'undefined') {
            $.ajax({
                method: "POST",
                url: "sendsap.transfer",
                data: {type: 'push', date_start: $('#startdate').val(), date_end: $('#enddate').val()},
                dataType: 'json',
                beforeSend: function () {
                    $('#spinner-div').show();
                },
                success: function (msg) {
                    $('#spinner-div').hide();
                    if (msg.status == 'success') {
                        alert("Transfer completed. System success send data to SAP.");
                    } else {
                        var txt = 'Transfer Completed.' + msg.message
                        alert(txt)
                    }
                }
            })
        }
    })
})

$('#date_radio_button').change(function () {
    if ($("#date_radio_button").is(':checked')) {
        $('#date_picker').prop("disabled", false);
    } else {
        $('#date_picker').prop("disabled", true);
    }

});
$(".disable_on_submit").click(function (e) {
    $(this).prop('disabled', true);
});
function printDiv(divID) {
    //Get the HTML of div
    var divElements = document.getElementById(divID).innerHTML;
    //Get the HTML of whole page
    var oldPage = document.body.innerHTML;
    //Reset the page's HTML with div's HTML only
    document.body.innerHTML =
            "<html><head><title></title></head><body>" +
            divElements + "</body>";
    //Print Page
    window.print();
    //Restore orignal HTML
    document.body.innerHTML = oldPage;
}

        </script>        
    </body>
</html>