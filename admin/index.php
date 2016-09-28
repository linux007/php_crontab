<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <script src="public/js/jquery.js"></script>
    <script src="public/js/script.js"></script>
    <script src="public/js/bootstrap.min.js"></script>
    <link href="public/css/bootstrap.min.css" rel="stylesheet">
    <title>Crontab Admin</title>
</head>
<body>
<div class="container">
    <h2>Cronjobs</h2>
    <ul class="breadcrumb">
        <li><a href="#">Home</a></li>
        <li class="active">Data</li>
    </ul>
    <div class="panel panel-default">
        <!-- Default panel contents -->
        <div class="panel-heading">Panel heading</div>
        <div class="panel-body">
            <p>
                <a class="btn btn-primary" onclick='newJob();'><span class="glyphicon glyphicon-edit" aria-hidden="true"></span>New Job</a>
            </p>
        </div>
        <table class="table table-striped">
            <thead>
            <th>#</th><th>Name</th><th>Job</th><th>Timer</th><th>Hostname</th><th>Last Modified</th><th></th>
            </thead>
            <?php if ($dataList): ?>
            <?php foreach ($dataList as $row): ?>
            <tr id="<?= $row['id'] ?>">
                <td><?= $row['id'] ?></td>
                <td label="name"><?= $row['name'] ?></td>
                <td label="command"><?= $row['command'] ?></td>
                <td label="schedule"><?= $row['schedule'] ?></td>
                <td label="schedule"><?= $row['hostname'] ?></td>
                <td label="updateAt"><?= date('Y-m-d H:i:s', $row['updateAt']) ?></td>
                <td>
                    <a class="btn btn-primary" onclick='editJob(<?=$row['id'] ?>)'><span class="glyphicon glyphicon-edit" aria-hidden="true"></span></a>
                    <!--<a class="btn btn-info"><span class="glyphicon glyphicon-stop" aria-hidden="true"></span> Stop</a>-->
                    <!--<a class="btn btn-info"><span class="glyphicon glyphicon-play" aria-hidden="true"></span> Start</a>-->
                    <a class="btn btn-danger" onclick="deleteJob(<?=$row['id'] ?>)"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

    <div class="modal fade" id="popup">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="modal-title">Message</h4>
                </div>
                <div class="modal-body" id="modal-body">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" id="modal-close-button">Close</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal" id="modal-button">Ok</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <div class="modal fade" id="info-popup">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="info-title">Message</h4>
                </div>
                <div class="modal-body" id="info-body">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal" id="info-button">Ok</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <!-- Job -->
    <div class="modal fade" id="job">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="job-title">Job</h4>
                </div>
                <div class="modal-body" id="job-body">
                    <label>Name (Optional)</label>
                    <input type='text' class='form-control' id='job-name'/><br />
                    <label>Command</label>
                    <input type='text' class='form-control' id='job-command' onkeyup="job_command = $(this).val(); job_string();"/><br />
                    <label>HostName</label>
                    <div class="row">
                        <div class="col-md-12">
                        <select class="form-control" id='job-host'>
                            <?php foreach ($serverHost as $val): ?>
                            <option><?=$val ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div><br />

<!--                    <label>Quick Schedule</label><br />-->
<!--                    <a class="btn btn-primary" onclick="schedule = '@reboot'; job_string();">Startup</a>-->
<!--                    <a class="btn btn-primary" onclick="schedule = '@hourly'; job_string();">Hourly</a>-->
<!--                    <a class="btn btn-primary" onclick="schedule = '@daily'; job_string();">Daily</a>-->
<!--                    <a class="btn btn-primary" onclick="schedule = '@weekly'; job_string();">Weekly</a>-->
<!--                    <a class="btn btn-primary" onclick="schedule = '@monthly'; job_string();">Monthly</a>-->
<!--                    <a class="btn btn-primary" onclick="schedule = '@yearly'; job_string();">Yearly</a><br /><br />-->
                    <div class="row">
                        <div class="col-md-2">Second</div>
                        <div class="col-md-2">Minute</div>
                        <div class="col-md-2">Hour</div>
                        <div class="col-md-2">Day</div>
                        <div class="col-md-2">Month</div>
                        <div class="col-md-2">Week</div>
                    </div>
                    <div class="row">
                        <div class="col-md-2"><input type="text" class="form-control" id="job-second" value="*" onclick="this.select();"/></div>
                        <div class="col-md-2"><input type="text" class="form-control" id="job-minute" value="*" onclick="this.select();"/></div>
                        <div class="col-md-2"><input type="text" class="form-control" id="job-hour" value="*" onclick="this.select();"/></div>
                        <div class="col-md-2"><input type="text" class="form-control" id="job-day" value="*" onclick="this.select();"/></div>
                        <div class="col-md-2"><input type="text" class="form-control" id="job-month" value="*" onclick="this.select();"/></div>
                        <div class="col-md-2"><input type="text" class="form-control" id="job-week" value="*" onclick="this.select();"/></div>
                    </div>
                    <br />
                    <div class="row">
                        <div class="col-md-2 pull-right"><a class="btn btn-primary col-md-offset-4" onclick="set_schedule();">Set</a></div>
                    </div>

                    <br />
                    <br />
                    <label>Job</label>
                    <input type='text' class='form-control' id='job-string' disabled='disabled'/><br />
<!--                    <label><input type="checkbox" id="job-logging" style="position:relative;top:2px"/> Enable error logging.</label>-->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal" id="job-save">Save</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

</div>
</body>
</html>