// exam_dashboard.js
$(function() {
  function loadExams(filters = {}) {
    $.get('exam_list.php', filters, function(html) {
      $('#examsTableContainer').html(html);
    });
  }

  $('#examForm').on('submit', function(e) {
    e.preventDefault();
    if (!$('#subject_name').val() || !$('#college_name').val() || !$('#exam_date').val() || !$('#start_time').val() || !$('#end_time').val() || !$('#max_marks').val() || !$('#pass_marks').val() || !$('#type').val() || !$('#status').val()) {
      $('#formMsg').html('<div class="alert alert-danger">Please fill all required fields.</div>');
      return;
    }
    $.post('exam_save.php', $(this).serialize(), function(resp) {
      $('#formMsg').html(resp.message);
      if (resp.success) {
        $('#examForm')[0].reset();
        loadExams();
      }
    }, 'json');
  });

  $('#filterForm').on('submit', function(e) {
    e.preventDefault();
    loadExams({search: $('#search').val()});
  });

  window.editExam = function(id) {
    $.get('exam_get.php', {id: id}, function(data) {
      Object.keys(data).forEach(function(key) {
        $('#' + key).val(data[key]);
      });
      $('#examId').val(id);
      $('#formMsg').html('');
      $('#examFormCard .card-header').text('Edit Exam');
    }, 'json');
  };

  window.deleteExam = function(id) {
    if (confirm('Are you sure you want to delete this exam?')) {
      $.post('exam_delete.php', {id: id}, function(resp) {
        alert(resp.message);
        if (resp.success) loadExams();
      }, 'json');
    }
  };

  window.approveExam = function(id) {
    if (confirm('Approve this exam?')) {
      $.post('exam_approve.php', {id: id}, function(resp) {
        alert(resp.message);
        if (resp.success) loadExams();
      }, 'json');
    }
  };

  window.viewExam = function(id) {
    $.get('exam_get.php', {id: id}, function(data) {
      let html = '<div class="modal" id="viewExamModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Exam Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">';
      Object.keys(data).forEach(function(key) {
        html += `<div><strong>${key.replace('_', ' ')}:</strong> ${data[key]}</div>`;
      });
      html += '</div></div></div></div>';
      $('body').append(html);
      $('#viewExamModal').modal('show').on('hidden.bs.modal', function() { $(this).remove(); });
    }, 'json');
  };

  loadExams();
  $('#resetBtn').on('click', function() {
    $('#examFormCard .card-header').text('Create Exam');
    $('#formMsg').html('');
    $('#examId').val('');
  });
});
