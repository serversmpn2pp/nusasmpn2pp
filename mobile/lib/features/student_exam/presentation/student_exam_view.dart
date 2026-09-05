import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_exam/application/student_exam_controller.dart';
import 'package:nusa/features/student_exam/data/exam_security_platform.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentExamView extends ConsumerStatefulWidget {
  const StudentExamView({required this.participantId, super.key});

  final int participantId;

  @override
  ConsumerState<StudentExamView> createState() => _StudentExamViewState();
}

class _StudentExamViewState extends ConsumerState<StudentExamView>
    with WidgetsBindingObserver {
  final _tokenController = TextEditingController();
  final _pageController = PageController();
  final Map<String, TextEditingController> _answerControllers = {};
  final Map<int, Timer> _saveTimers = {};
  final Map<int, int> _revisions = {};
  final Set<int> _dirtyQuestions = {};
  final Map<int, _AnswerSaveStatus> _saveStatuses = {};
  late final ExamSecurityPlatform _securityPlatform;
  StudentExamSession? _session;
  Timer? _countdownTimer;
  Timer? _heartbeatTimer;
  Future<void>? _awayRequest;
  DateTime? _localDeadline;
  int _remainingSeconds = 0;
  int _currentQuestion = 0;
  bool _opening = false;
  bool _finishing = false;
  bool _automaticFinishStarted = false;
  bool _allowPop = false;
  bool _awayReported = false;
  bool _initialLockedSessionScheduled = false;

  @override
  void initState() {
    super.initState();
    _securityPlatform = ref.read(examSecurityPlatformProvider);
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _updateCountdown();
      unawaited(_handleResume());
      return;
    }
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.paused) {
      _handleAway();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _countdownTimer?.cancel();
    _heartbeatTimer?.cancel();
    for (final timer in _saveTimers.values) {
      timer.cancel();
    }
    for (final controller in _answerControllers.values) {
      controller.dispose();
    }
    _tokenController.dispose();
    _pageController.dispose();
    unawaited(_securityPlatform.leave());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentExamProvider(widget.participantId));
    final data = _session ?? state.value;
    if (data?.isLocked == true &&
        _session == null &&
        !_initialLockedSessionScheduled) {
      _initialLockedSessionScheduled = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _session == null) _activate(data!);
      });
    }
    return PopScope(
      canPop: data?.isRunning != true || _allowPop,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) unawaited(_confirmLeave());
      },
      child: Scaffold(
        backgroundColor: NusaColors.background,
        appBar: AppBar(
          title: Text(
            data?.isRunning == true || data?.isLocked == true
                ? 'Ujian'
                : 'Ujian Saya',
          ),
          actions: [
            if (data?.isRunning == true || data?.isLocked == true)
              Padding(
                padding: const EdgeInsets.only(right: 12),
                child: Center(child: _TimerPill(seconds: _remainingSeconds)),
              )
            else if (!state.isLoading)
              IconButton(
                tooltip: 'Perbarui',
                onPressed: () {
                  setState(() => _session = null);
                  ref.invalidate(studentExamProvider(widget.participantId));
                },
                icon: const Icon(Icons.refresh_rounded),
              ),
          ],
        ),
        body: SafeArea(
          top: false,
          child: data != null
              ? _content(data)
              : state.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (error, stackTrace) => _ErrorState(
                    message: _message(error),
                    onRetry: () => ref.invalidate(
                      studentExamProvider(widget.participantId),
                    ),
                  ),
                  data: _content,
                ),
        ),
      ),
    );
  }

  Widget _content(StudentExamSession session) {
    if (session.isRunning) return _running(session);
    if (session.isLocked) return _locked(session);
    if (session.isCompleted) return _completed(session);
    return _confirmation(session);
  }

  Widget _confirmation(StudentExamSession session) => ListView(
    key: const Key('student-exam-confirmation'),
    keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
    children: [
      _ExamHero(exam: session.exam, participant: session.participant),
      const SizedBox(height: 12),
      _ProgressCard(progress: session.progress),
      const SizedBox(height: 12),
      _InformationCard(session: session),
      const SizedBox(height: 12),
      _InstructionCard(instructions: session.exam.instructions),
      if (session.requiresToken) ...[
        const SizedBox(height: 12),
        NusaTextField(
          fieldKey: const Key('student-exam-token'),
          controller: _tokenController,
          hintText: 'Token dari pengawas',
          labelText: 'Token ujian',
          prefixIcon: Icons.key_rounded,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _openExam(session),
        ),
      ],
      const SizedBox(height: 16),
      NusaPrimaryButton(
        key: const Key('student-exam-open'),
        label: session.participant.status == 'sedang_mengerjakan'
            ? 'Lanjutkan Ujian'
            : 'Mulai Ujian',
        loading: _opening,
        onPressed: session.canStart ? () => _openExam(session) : null,
      ),
      if (!session.canStart) ...[
        const SizedBox(height: 10),
        const _Notice(
          message: 'Ujian belum dapat dimulai. Periksa kembali jadwal atau hubungi pengawas.',
          warning: true,
        ),
      ],
    ],
  );

  Widget _running(StudentExamSession session) {
    if (session.questions.isEmpty) {
      return const _ErrorState(
        message: 'Paket ujian belum memiliki soal yang dapat ditampilkan.',
      );
    }
    final current = session
        .questions[_currentQuestion.clamp(0, session.questions.length - 1)];
    return Column(
      children: [
        if (session.security.enabled)
          _SafeModeBanner(security: session.security),
        _ExamStatusBar(
          subject: session.exam.subject,
          current: _currentQuestion + 1,
          total: session.questions.length,
          progress: session.progress,
          onNavigate: () => _showQuestionNavigator(session),
        ),
        Expanded(
          child: PageView.builder(
            key: const Key('student-exam-question-pages'),
            controller: _pageController,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: session.questions.length,
            itemBuilder: (context, index) => _QuestionPage(
              question: session.questions[index],
              saveStatus:
                  _saveStatuses[session.questions[index].id] ??
                  _AnswerSaveStatus.saved,
              controllerFor: _controllerFor,
              onAnswerChanged: (answer) =>
                  _changeAnswer(session.questions[index], answer),
              onDoubtChanged: (value) =>
                  _changeDoubt(session.questions[index], value),
            ),
          ),
        ),
        _ExamNavigationBar(
          current: _currentQuestion,
          total: session.questions.length,
          finishing: _finishing,
          saveStatus: _saveStatuses[current.id] ?? _AnswerSaveStatus.saved,
          onPrevious: _currentQuestion > 0
              ? () => _goToQuestion(_currentQuestion - 1)
              : null,
          onNext: _currentQuestion < session.questions.length - 1
              ? () => _goToQuestion(_currentQuestion + 1)
              : null,
          onFinish: () => _confirmFinish(session),
        ),
      ],
    );
  }

  Widget _completed(StudentExamSession session) => ListView(
    key: const Key('student-exam-completed'),
    padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
    children: [
      const _CompletionIcon(),
      const SizedBox(height: 18),
      Text(
        'Ujian telah selesai',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.headlineSmall
            ?.copyWith(color: NusaColors.primary, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 6),
      Text(
        'Jawaban ${session.participant.name} sudah tersimpan di NUSA.',
        textAlign: TextAlign.center,
        style: const TextStyle(color: NusaColors.textSecondary),
      ),
      const SizedBox(height: 20),
      _ExamHero(exam: session.exam, participant: session.participant),
      const SizedBox(height: 12),
      _ProgressCard(progress: session.progress),
      const SizedBox(height: 12),
      _ResultCard(result: session.result),
      const SizedBox(height: 18),
      FilledButton.icon(
        key: const Key('student-exam-back-to-list'),
        onPressed: () => context.pop(),
        icon: const Icon(Icons.arrow_back_rounded),
        label: const Text('Kembali ke Ujian Saya'),
      ),
    ],
  );

  Widget _locked(StudentExamSession session) => ListView(
    key: const Key('student-exam-locked'),
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 30),
    children: [
      const Icon(Icons.gpp_bad_rounded, size: 74, color: Color(0xFFC0392B)),
      const SizedBox(height: 14),
      Text(
        'Ujian sementara ditahan',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.headlineSmall
            ?.copyWith(color: NusaColors.primary, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 8),
      const Text(
        'Batas keluar dari aplikasi NUSA telah tercapai. Waktu ujian tetap berjalan. Silakan hubungi pengawas.',
        textAlign: TextAlign.center,
        style: TextStyle(color: NusaColors.textSecondary, height: 1.45),
      ),
      const SizedBox(height: 18),
      _SafeModeBanner(security: session.security, locked: true),
      const SizedBox(height: 18),
      FilledButton.icon(
        key: const Key('student-exam-check-lock'),
        onPressed: _opening ? null : _checkLockStatus,
        icon: _opening
            ? const SizedBox.square(
                dimension: 17,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : const Icon(Icons.sync_rounded),
        label: const Text('Periksa Status Ujian'),
      ),
      const SizedBox(height: 10),
      TextButton.icon(
        onPressed: () => context.pop(),
        icon: const Icon(Icons.arrow_back_rounded),
        label: const Text('Kembali ke daftar ujian'),
      ),
    ],
  );

  Future<void> _openExam(StudentExamSession session) async {
    if (_opening) return;
    if (session.requiresToken && _tokenController.text.trim().isEmpty) {
      _showMessage(
        'Masukkan token dari pengawas terlebih dahulu.',
        error: true,
      );
      return;
    }
    setState(() => _opening = true);
    try {
      final actions = ref.read(studentExamActionsProvider);
      final result = session.participant.status == 'sedang_mengerjakan'
          ? await actions.resume(widget.participantId)
          : await actions.start(
              widget.participantId,
              _tokenController.text.trim(),
            );
      if (!mounted) return;
      _activate(result);
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  void _activate(StudentExamSession session) {
    _countdownTimer?.cancel();
    _heartbeatTimer?.cancel();
    _clearAnswerControllers();
    setState(() {
      _session = session;
      _currentQuestion = 0;
      _remainingSeconds = session.remainingSeconds;
      _localDeadline = DateTime.now().add(
        Duration(seconds: session.remainingSeconds),
      );
      _automaticFinishStarted = false;
      _dirtyQuestions.clear();
      _saveStatuses.clear();
      _revisions.clear();
    });
    unawaited(_applyPlatformSecurity(session));
    if (session.isRunning || session.isLocked) {
      _countdownTimer = Timer.periodic(
        const Duration(seconds: 1),
        (_) => _updateCountdown(),
      );
    }
    if (session.isRunning && session.security.enabled) {
      _heartbeatTimer = Timer.periodic(
        const Duration(seconds: 15),
        (_) => unawaited(_heartbeat()),
      );
      unawaited(_heartbeat());
    }
  }

  void _updateCountdown() {
    if (!mounted ||
        _localDeadline == null ||
        (_session?.isRunning != true && _session?.isLocked != true)) {
      return;
    }
    final remaining = _localDeadline!.difference(DateTime.now()).inSeconds;
    final next = remaining.clamp(0, 1 << 31);
    if (next != _remainingSeconds) setState(() => _remainingSeconds = next);
    if (next == 0 && !_automaticFinishStarted) {
      _automaticFinishStarted = true;
      unawaited(_finishAutomatically());
    }
  }

  void _handleAway() {
    final session = _session;
    if (session == null ||
        !session.isRunning ||
        !session.security.trackAppSwitch ||
        _awayReported) {
      return;
    }
    _awayReported = true;
    _awayRequest = _reportAway();
  }

  Future<void> _reportAway() async {
    try {
      await Future.wait<void>([
        ref
            .read(studentExamActionsProvider)
            .securityEvent(widget.participantId, 'keluar')
            .then((_) {}),
        _flushAnswers().then((_) {}),
      ]);
    } catch (_) {
      // Gangguan jaringan ditangani saat aplikasi aktif kembali.
    }
  }

  Future<void> _handleResume() async {
    if (!_awayReported || _session?.security.trackAppSwitch != true) return;
    final pending = _awayRequest;
    if (pending != null) await pending;
    try {
      final update = await ref
          .read(studentExamActionsProvider)
          .securityEvent(widget.participantId, 'kembali');
      if (!mounted) return;
      _applySecurityUpdate(update);
    } catch (_) {
      // Jawaban tetap dapat dilanjutkan; heartbeat akan menyelaraskan status.
    } finally {
      _awayRequest = null;
      _awayReported = false;
    }
  }

  Future<void> _heartbeat() async {
    if (_session?.isRunning != true) return;
    try {
      final update = await ref
          .read(studentExamActionsProvider)
          .securityEvent(widget.participantId, 'heartbeat');
      if (mounted) _applySecurityUpdate(update, notify: false);
    } catch (_) {
      // Heartbeat bersifat best effort dan tidak mengganggu pengerjaan.
    }
  }

  void _applySecurityUpdate(
    StudentExamSecurityUpdate update, {
    bool notify = true,
  }) {
    final session = _session;
    if (session == null || session.isCompleted) return;
    final locked = update.mode == 'ditahan' || update.security.locked;
    setState(() {
      _session = session.copyWith(
        mode: locked ? 'ditahan' : session.mode,
        security: update.security,
      );
    });
    if (locked) _heartbeatTimer?.cancel();
    if (notify && update.counted && update.message?.isNotEmpty == true) {
      _showMessage(update.message!, error: locked);
    }
  }

  Future<void> _checkLockStatus() async {
    if (_opening) return;
    setState(() => _opening = true);
    try {
      final result = await ref
          .read(studentExamActionsProvider)
          .resume(widget.participantId);
      if (!mounted) return;
      _activate(result);
      _showMessage(
        result.isRunning
            ? 'Ujian sudah dibuka oleh pengawas. Silakan lanjutkan.'
            : 'Ujian masih ditahan. Hubungi pengawas.',
        error: result.isLocked,
      );
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  Future<void> _applyPlatformSecurity(StudentExamSession session) async {
    try {
      if (session.isRunning || session.isLocked) {
        await _securityPlatform.enter(
          secureScreen: session.security.secureScreen,
          fullscreen: session.security.requireFullscreen,
        );
      } else {
        await _securityPlatform.leave();
      }
    } catch (_) {
      // Proteksi server tetap aktif bila perangkat tidak mendukung fitur ini.
    }
  }

  void _changeAnswer(StudentExamQuestion question, Map<String, String> answer) {
    _replaceQuestion(question.copyWith(answer: answer));
  }

  void _changeDoubt(StudentExamQuestion question, bool value) {
    final latest = _questionById(question.id) ?? question;
    _replaceQuestion(latest.copyWith(doubtful: value));
  }

  void _replaceQuestion(StudentExamQuestion changed) {
    final session = _session;
    if (session == null || !session.isRunning) return;
    final questions = session.questions
        .map((item) => item.id == changed.id ? changed : item)
        .toList(growable: false);
    final progress = _progressFor(questions);
    final revision = (_revisions[changed.id] ?? 0) + 1;
    _saveTimers[changed.id]?.cancel();
    setState(() {
      _session = session.copyWith(questions: questions, progress: progress);
      _revisions[changed.id] = revision;
      _dirtyQuestions.add(changed.id);
      _saveStatuses[changed.id] = _AnswerSaveStatus.pending;
    });
    _saveTimers[changed.id] = Timer(
      const Duration(milliseconds: 700),
      () => unawaited(_saveQuestion(changed.id)),
    );
  }

  StudentExamProgress _progressFor(List<StudentExamQuestion> questions) {
    final answered = questions.where((item) => item.isAnswered).length;
    return StudentExamProgress(
      questionCount: questions.length,
      answered: answered,
      unanswered: questions.length - answered,
      doubtful: questions.where((item) => item.doubtful).length,
    );
  }

  Future<bool> _saveQuestion(int questionId) async {
    final question = _questionById(questionId);
    if (question == null || _session?.isRunning != true) return true;
    final revision = _revisions[questionId] ?? 0;
    if (mounted) {
      setState(() => _saveStatuses[questionId] = _AnswerSaveStatus.saving);
    }
    try {
      final result = await ref
          .read(studentExamActionsProvider)
          .saveAnswer(participantId: widget.participantId, question: question);
      if (!mounted) return true;
      if (result.completed && result.session != null) {
        _activate(result.session!);
        return true;
      }
      setState(() {
        if ((_revisions[questionId] ?? 0) == revision) {
          _dirtyQuestions.remove(questionId);
          _saveStatuses[questionId] = _AnswerSaveStatus.saved;
        } else {
          _saveStatuses[questionId] = _AnswerSaveStatus.pending;
        }
        if (result.remainingSeconds > 0) {
          _remainingSeconds = result.remainingSeconds;
          _localDeadline = DateTime.now().add(
            Duration(seconds: result.remainingSeconds),
          );
        }
      });
      if ((_revisions[questionId] ?? 0) != revision) {
        _saveTimers[questionId]?.cancel();
        _saveTimers[questionId] = Timer(
          const Duration(milliseconds: 400),
          () => unawaited(_saveQuestion(questionId)),
        );
      }
      return true;
    } catch (error) {
      if (mounted) {
        setState(() => _saveStatuses[questionId] = _AnswerSaveStatus.failed);
      }
      return false;
    }
  }

  Future<bool> _flushAnswers() async {
    for (final timer in _saveTimers.values) {
      timer.cancel();
    }
    final pending = _dirtyQuestions.toList(growable: false);
    for (final id in pending) {
      if (!await _saveQuestion(id)) return false;
      if (_session?.isCompleted == true) return true;
    }
    return true;
  }

  Future<void> _confirmFinish(StudentExamSession session) async {
    if (_finishing) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Selesaikan ujian?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${session.progress.answered} terjawab, '
              '${session.progress.unanswered} belum dijawab, dan '
              '${session.progress.doubtful} ditandai ragu-ragu.',
            ),
            const SizedBox(height: 10),
            const Text(
              'Setelah dikumpulkan, jawaban tidak dapat diubah lagi.',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Periksa Lagi'),
          ),
          FilledButton(
            key: const Key('student-exam-confirm-finish'),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Kumpulkan'),
          ),
        ],
      ),
    );
    if (confirmed == true) await _finish();
  }

  Future<void> _finish() async {
    if (_finishing) return;
    setState(() => _finishing = true);
    try {
      final saved = await _flushAnswers();
      if (!saved) {
        _showMessage(
          'Ada jawaban yang belum tersimpan. Periksa koneksi lalu coba lagi.',
          error: true,
        );
        return;
      }
      if (_session?.isCompleted == true) return;
      final result = await ref
          .read(studentExamActionsProvider)
          .finish(widget.participantId);
      if (mounted) _activate(result);
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _finishing = false);
    }
  }

  Future<void> _finishAutomatically() async {
    try {
      final result = await ref
          .read(studentExamActionsProvider)
          .finish(widget.participantId);
      if (!mounted) return;
      _activate(result);
      _showMessage('Waktu habis. Jawaban yang tersimpan telah dikumpulkan.');
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    }
  }

  Future<void> _confirmLeave() async {
    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Keluar dari pengerjaan?'),
        content: const Text(
          'Ujian tidak akan diselesaikan. Jawaban akan disimpan dan dapat dilanjutkan selama waktu masih tersedia.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Tetap di Ujian'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Simpan & Keluar'),
          ),
        ],
      ),
    );
    if (leave != true) return;
    final saved = await _flushAnswers();
    if (!mounted) return;
    if (!saved) {
      _showMessage('Jawaban belum seluruhnya tersimpan.', error: true);
      return;
    }
    setState(() => _allowPop = true);
    context.pop();
  }

  void _goToQuestion(int index) {
    if (index < 0 || index >= (_session?.questions.length ?? 0)) return;
    setState(() => _currentQuestion = index);
    _pageController.animateToPage(
      index,
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _showQuestionNavigator(StudentExamSession session) async {
    final index = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Navigasi Soal',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 5),
              const Text(
                'Biru: aktif · Hijau: terjawab · Kuning: ragu-ragu',
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
              ),
              const SizedBox(height: 14),
              Flexible(
                child: GridView.builder(
                  shrinkWrap: true,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 6,
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                  ),
                  itemCount: session.questions.length,
                  itemBuilder: (context, itemIndex) {
                    final question = session.questions[itemIndex];
                    final color = itemIndex == _currentQuestion
                        ? NusaColors.primary
                        : question.doubtful
                        ? NusaColors.accent
                        : question.isAnswered
                        ? NusaColors.success
                        : NusaColors.outline;
                    final foreground = color == NusaColors.outline
                        ? NusaColors.textPrimary
                        : itemIndex == _currentQuestion || question.isAnswered
                        ? Colors.white
                        : NusaColors.textPrimary;
                    return InkWell(
                      onTap: () => Navigator.pop(context, itemIndex),
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: color,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '${itemIndex + 1}',
                          style: TextStyle(
                            color: foreground,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (index != null) _goToQuestion(index);
  }

  StudentExamQuestion? _questionById(int id) {
    for (final item in _session?.questions ?? const <StudentExamQuestion>[]) {
      if (item.id == id) return item;
    }
    return null;
  }

  TextEditingController _controllerFor(
    StudentExamQuestion question,
    String field,
  ) {
    final key = '${question.id}:$field';
    return _answerControllers.putIfAbsent(
      key,
      () => TextEditingController(
        text: field == 'value'
            ? (question.answer.values.isEmpty
                  ? ''
                  : question.answer.values.first)
            : question.answer[field] ?? '',
      ),
    );
  }

  void _clearAnswerControllers() {
    for (final controller in _answerControllers.values) {
      controller.dispose();
    }
    _answerControllers.clear();
  }

  void _showMessage(String message, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: error ? Theme.of(context).colorScheme.error : null,
          content: Text(message),
        ),
      );
  }
}

class _SafeModeBanner extends StatelessWidget {
  const _SafeModeBanner({required this.security, this.locked = false});

  final StudentExamSecurity security;
  final bool locked;

  @override
  Widget build(BuildContext context) {
    final color = locked ? const Color(0xFFC0392B) : NusaColors.primary;
    final strict = security.trackAppSwitch && security.action == 'tahan';
    return Container(
      key: const Key('student-exam-safe-mode'),
      margin: locked
          ? EdgeInsets.zero
          : const EdgeInsets.fromLTRB(12, 8, 12, 0),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Icon(
            locked ? Icons.lock_clock_rounded : Icons.verified_user_rounded,
            size: 19,
            color: color,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              locked
                  ? 'Mode Aman menahan ujian · ${security.incidentCount} kejadian · ${security.totalAwaySeconds} detik di luar NUSA'
                  : strict
                  ? 'Mode Aman aktif · Peringatan ${security.incidentCount}/${security.incidentLimit}'
                  : 'Mode Aman aktif · Aktivitas keluar aplikasi dicatat',
              style: TextStyle(
                color: color,
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _QuestionPage extends StatelessWidget {
  const _QuestionPage({
    required this.question,
    required this.saveStatus,
    required this.controllerFor,
    required this.onAnswerChanged,
    required this.onDoubtChanged,
  });

  final StudentExamQuestion question;
  final _AnswerSaveStatus saveStatus;
  final TextEditingController Function(StudentExamQuestion, String)
  controllerFor;
  final ValueChanged<Map<String, String>> onAnswerChanged;
  final ValueChanged<bool> onDoubtChanged;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    key: Key('student-exam-question-${question.id}'),
    keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: NusaColors.primary,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${question.number}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    question.typeLabel,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                _SaveStatusLabel(status: saveStatus),
              ],
            ),
            if (question.stimulus?.trim().isNotEmpty == true) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Text(question.stimulus!),
              ),
            ],
            if (_hasMedia(question.media)) ...[
              const SizedBox(height: 14),
              _QuestionMedia(media: question.media),
            ],
            const SizedBox(height: 18),
            Text(
              question.question,
              style: const TextStyle(
                fontSize: 16,
                height: 1.45,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 16),
            _answerInput(),
            const SizedBox(height: 12),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: question.doubtful,
              activeTrackColor: NusaColors.accent,
              title: const Text(
                'Tandai ragu-ragu',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              subtitle: const Text('Kembali periksa sebelum dikumpulkan.'),
              onChanged: onDoubtChanged,
            ),
          ],
        ),
      ),
    ),
  );

  Widget _answerInput() => switch (question.type) {
    'pilihan_ganda' => _choiceOptions(multiple: false),
    'pilihan_ganda_kompleks' => _choiceOptions(multiple: true),
    'benar_salah' => _trueFalse(),
    'menjodohkan' => _matching(),
    'isian_singkat' => _textAnswer(lines: 1),
    'numerik' => _textAnswer(lines: 1, numeric: true),
    _ => _textAnswer(lines: 6),
  };

  Widget _choiceOptions({required bool multiple}) {
    final selected = question.answer.values.toSet();
    return Column(
      children: question.options
          .map((option) {
            final checked = selected.contains(option.code);
            return Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: InkWell(
                onTap: () {
                  final next = <String>{...selected};
                  if (multiple) {
                    checked ? next.remove(option.code) : next.add(option.code);
                  } else {
                    next
                      ..clear()
                      ..add(option.code);
                  }
                  final sorted = next.toList()..sort();
                  onAnswerChanged({
                    for (var index = 0; index < sorted.length; index++)
                      '$index': sorted[index],
                  });
                },
                borderRadius: BorderRadius.circular(14),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: checked
                        ? NusaColors.surfaceBlue
                        : NusaColors.surface,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: checked ? NusaColors.primary : NusaColors.outline,
                      width: checked ? 1.5 : 1,
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 28,
                        height: 28,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: checked
                              ? NusaColors.primary
                              : NusaColors.background,
                          shape: multiple
                              ? BoxShape.rectangle
                              : BoxShape.circle,
                          borderRadius: multiple
                              ? BorderRadius.circular(7)
                              : null,
                          border: checked
                              ? null
                              : Border.all(color: NusaColors.outline),
                        ),
                        child: checked
                            ? Icon(
                                multiple ? Icons.check_rounded : Icons.circle,
                                color: Colors.white,
                                size: multiple ? 19 : 10,
                              )
                            : Text(
                                option.code,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                      ),
                      const SizedBox(width: 11),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(option.text),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          })
          .toList(growable: false),
    );
  }

  Widget _trueFalse() => Column(
    children: question.statements
        .map((statement) {
          final current = question.answer[statement.number];
          return Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              border: Border.all(color: NusaColors.outline),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${statement.number}. ${statement.text}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  children: ['benar', 'salah']
                      .map((value) {
                        return ChoiceChip(
                          label: Text(value == 'benar' ? 'Benar' : 'Salah'),
                          selected: current == value,
                          onSelected: (_) {
                            final answer = {...question.answer};
                            answer[statement.number] = value;
                            onAnswerChanged(answer);
                          },
                        );
                      })
                      .toList(growable: false),
                ),
              ],
            ),
          );
        })
        .toList(growable: false),
  );

  Widget _matching() => Column(
    children: question.pairs
        .map((pair) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: TextField(
              key: Key('student-exam-match-${question.id}-${pair.number}'),
              controller: controllerFor(question, pair.number),
              enableInteractiveSelection: false,
              onChanged: (value) {
                final answer = {...question.answer, pair.number: value};
                onAnswerChanged(answer);
              },
              decoration: InputDecoration(
                labelText: '${pair.number}. ${pair.left}',
                hintText: 'Tulis pasangan jawaban',
              ),
            ),
          );
        })
        .toList(growable: false),
  );

  Widget _textAnswer({required int lines, bool numeric = false}) => TextField(
    key: Key('student-exam-answer-${question.id}'),
    controller: controllerFor(question, 'value'),
    enableInteractiveSelection: false,
    keyboardType: numeric
        ? const TextInputType.numberWithOptions(decimal: true, signed: true)
        : TextInputType.multiline,
    minLines: lines,
    maxLines: lines == 1 ? 1 : null,
    onChanged: (value) => onAnswerChanged({'0': value}),
    decoration: InputDecoration(
      labelText: numeric ? 'Jawaban angka' : 'Jawaban',
      hintText: lines == 1 ? 'Tulis jawaban' : 'Tulis jawaban di sini',
      alignLabelWithHint: lines > 1,
    ),
  );
}

class _ExamStatusBar extends StatelessWidget {
  const _ExamStatusBar({
    required this.subject,
    required this.current,
    required this.total,
    required this.progress,
    required this.onNavigate,
  });
  final String subject;
  final int current;
  final int total;
  final StudentExamProgress progress;
  final VoidCallback onNavigate;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 11),
    decoration: const BoxDecoration(
      color: Colors.white,
      border: Border(bottom: BorderSide(color: NusaColors.outline)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                subject,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              Text(
                '${progress.answered} terjawab · ${progress.doubtful} ragu-ragu',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
            ],
          ),
        ),
        TextButton.icon(
          key: const Key('student-exam-navigator'),
          onPressed: onNavigate,
          icon: const Icon(Icons.grid_view_rounded, size: 18),
          label: Text('$current / $total'),
        ),
      ],
    ),
  );
}

class _ExamNavigationBar extends StatelessWidget {
  const _ExamNavigationBar({
    required this.current,
    required this.total,
    required this.finishing,
    required this.saveStatus,
    required this.onPrevious,
    required this.onNext,
    required this.onFinish,
  });
  final int current;
  final int total;
  final bool finishing;
  final _AnswerSaveStatus saveStatus;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;
  final VoidCallback onFinish;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.fromLTRB(
      12,
      9,
      12,
      9 + MediaQuery.paddingOf(context).bottom,
    ),
    decoration: BoxDecoration(
      color: Colors.white,
      border: const Border(top: BorderSide(color: NusaColors.outline)),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.06),
          blurRadius: 16,
          offset: const Offset(0, -4),
        ),
      ],
    ),
    child: Row(
      children: [
        IconButton.filledTonal(
          tooltip: 'Sebelumnya',
          onPressed: onPrevious,
          icon: const Icon(Icons.arrow_back_rounded),
        ),
        const SizedBox(width: 8),
        Expanded(child: _SaveStatusLabel(status: saveStatus, centered: true)),
        const SizedBox(width: 8),
        if (onNext != null)
          FilledButton.icon(
            key: const Key('student-exam-next'),
            style: FilledButton.styleFrom(minimumSize: const Size(0, 48)),
            onPressed: onNext,
            icon: const Icon(Icons.arrow_forward_rounded),
            label: const Text('Berikutnya'),
          )
        else
          FilledButton.icon(
            key: const Key('student-exam-finish'),
            style: FilledButton.styleFrom(minimumSize: const Size(0, 48)),
            onPressed: finishing ? null : onFinish,
            icon: finishing
                ? const SizedBox.square(
                    dimension: 17,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.task_alt_rounded),
            label: const Text('Selesai'),
          ),
      ],
    ),
  );
}

class _TimerPill extends StatelessWidget {
  const _TimerPill({required this.seconds});
  final int seconds;

  @override
  Widget build(BuildContext context) {
    final urgent = seconds <= 300;
    return Container(
      key: const Key('student-exam-timer'),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: urgent ? const Color(0xFFFFECEA) : const Color(0xFFFFF8D7),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: urgent ? const Color(0xFFE36A5D) : NusaColors.accent,
        ),
      ),
      child: Text(
        _duration(seconds),
        style: TextStyle(
          color: urgent ? const Color(0xFFAA2E25) : NusaColors.primaryDark,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SaveStatusLabel extends StatelessWidget {
  const _SaveStatusLabel({required this.status, this.centered = false});
  final _AnswerSaveStatus status;
  final bool centered;

  @override
  Widget build(BuildContext context) {
    final (icon, label, color) = switch (status) {
      _AnswerSaveStatus.pending => (
        Icons.cloud_upload_outlined,
        'Belum tersimpan',
        const Color(0xFFE59A00),
      ),
      _AnswerSaveStatus.saving => (
        Icons.sync_rounded,
        'Menyimpan...',
        NusaColors.primary,
      ),
      _AnswerSaveStatus.saved => (
        Icons.cloud_done_rounded,
        'Tersimpan',
        NusaColors.success,
      ),
      _AnswerSaveStatus.failed => (
        Icons.cloud_off_rounded,
        'Gagal tersimpan',
        const Color(0xFFC0392B),
      ),
    };
    return LayoutBuilder(
      builder: (context, constraints) {
        final iconOnly = centered && constraints.maxWidth < 64;
        return Tooltip(
          message: label,
          child: Row(
            mainAxisSize: centered ? MainAxisSize.max : MainAxisSize.min,
            mainAxisAlignment: centered
                ? MainAxisAlignment.center
                : MainAxisAlignment.start,
            children: [
              Icon(icon, size: 15, color: color),
              if (!iconOnly) ...[
                const SizedBox(width: 4),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: color,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _ExamHero extends StatelessWidget {
  const _ExamHero({required this.exam, required this.participant});
  final StudentExamInfo exam;
  final StudentExamParticipant participant;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.18),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 52,
          height: 52,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: NusaColors.accent),
          ),
          child: const Icon(Icons.quiz_rounded, color: NusaColors.accent),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                exam.subject,
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                exam.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                '${participant.name} · ${participant.schoolClass}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.78),
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ProgressCard extends StatelessWidget {
  const _ProgressCard({required this.progress});
  final StudentExamProgress progress;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 13),
      child: Row(
        children: [
          _metric('Soal', progress.questionCount, NusaColors.primary),
          _metric('Terjawab', progress.answered, NusaColors.success),
          _metric('Belum', progress.unanswered, const Color(0xFFE59A00)),
          _metric('Ragu', progress.doubtful, const Color(0xFFC08600)),
        ],
      ),
    ),
  );

  Widget _metric(String label, int value, Color color) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: color,
            fontSize: 19,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class _InformationCard extends StatelessWidget {
  const _InformationCard({required this.session});
  final StudentExamSession session;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Informasi Ujian',
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          _InfoRow(
            icon: Icons.badge_outlined,
            label: 'Nomor peserta',
            value: session.participant.participantNumber ?? '-',
          ),
          _InfoRow(
            icon: Icons.schedule_rounded,
            label: 'Durasi',
            value: '${session.exam.durationMinutes} menit',
          ),
          _InfoRow(
            icon: Icons.meeting_room_outlined,
            label: 'Ruang / meja',
            value:
                [
                      session.participant.room,
                      if (session.participant.seatNumber != null)
                        'Meja ${session.participant.seatNumber}',
                    ]
                    .whereType<String>()
                    .where((value) => value.isNotEmpty)
                    .join(' · ')
                    .ifEmpty('-'),
          ),
          _InfoRow(
            icon: Icons.security_rounded,
            label: 'Keamanan',
            value: session.exam.singleDevice
                ? 'Dibatasi satu perangkat'
                : 'Perangkat tidak dibatasi',
            last: true,
          ),
        ],
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.last = false,
  });
  final IconData icon;
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: last ? 0 : 11),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: NusaColors.primary),
        const SizedBox(width: 9),
        SizedBox(
          width: 94,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
          ),
        ),
      ],
    ),
  );
}

class _InstructionCard extends StatelessWidget {
  const _InstructionCard({this.instructions});
  final String? instructions;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.menu_book_rounded, color: NusaColors.primary),
              SizedBox(width: 8),
              Text(
                'Petunjuk Ujian',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            instructions?.trim().isNotEmpty == true ? instructions! : 'Baca setiap soal dengan teliti, kerjakan secara jujur, dan pastikan jawaban tersimpan sebelum selesai.',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              height: 1.45,
            ),
          ),
        ],
      ),
    ),
  );
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({this.result});
  final StudentExamResult? result;

  @override
  Widget build(BuildContext context) {
    final value = result;
    if (value?.visible == true && value?.score != null) {
      final passed = value?.passed;
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            children: [
              const Text(
                'Nilai Ujian',
                style: TextStyle(
                  color: NusaColors.textSecondary,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                _number(value!.score!),
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 38,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (passed != null)
                Text(
                  passed ? 'Tuntas' : 'Belum tuntas',
                  style: TextStyle(
                    color: passed
                        ? NusaColors.success
                        : const Color(0xFFC0392B),
                    fontWeight: FontWeight.w900,
                  ),
                ),
            ],
          ),
        ),
      );
    }
    return _Notice(
      message: value?.awaitingCorrection == true
          ? 'Nilai akan tampil setelah guru menyelesaikan koreksi jawaban uraian.'
          : 'Nilai tidak ditampilkan untuk ujian ini. Informasi hasil diberikan oleh guru.',
    );
  }
}

class _QuestionMedia extends StatelessWidget {
  const _QuestionMedia({required this.media});
  final StudentExamMedia media;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (media.image case final image? when image.url.isNotEmpty) ...[
        ClipRRect(
          borderRadius: BorderRadius.circular(14),
          child: Image.network(
            image.url,
            fit: BoxFit.contain,
            errorBuilder: (context, error, stackTrace) => const _Notice(
              message: 'Gambar soal belum dapat dimuat.',
              warning: true,
            ),
          ),
        ),
        if (image.caption?.isNotEmpty == true)
          Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Text(
              image.caption!,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 10,
              ),
            ),
          ),
      ],
      if (media.table case final table? when table.rows.isNotEmpty) ...[
        if (table.title?.isNotEmpty == true)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text(
              table.title!,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Table(
            defaultColumnWidth: const IntrinsicColumnWidth(),
            border: TableBorder.all(color: NusaColors.outline),
            children: table.rows
                .map(
                  (row) => TableRow(
                    children: row
                        .map(
                          (cell) => Padding(
                            padding: const EdgeInsets.all(8),
                            child: Text(cell),
                          ),
                        )
                        .toList(growable: false),
                  ),
                )
                .toList(growable: false),
          ),
        ),
      ],
      if (media.formula case final formula? when formula.latex.isNotEmpty)
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: NusaColors.surfaceBlue,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(
            formula.latex,
            textAlign: TextAlign.center,
            style: const TextStyle(fontFamily: 'monospace'),
          ),
        ),
    ],
  );
}

class _CompletionIcon extends StatelessWidget {
  const _CompletionIcon();

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 92,
      height: 92,
      decoration: BoxDecoration(
        color: NusaColors.successSurface,
        shape: BoxShape.circle,
        border: Border.all(
          color: NusaColors.success.withValues(alpha: 0.35),
          width: 2,
        ),
      ),
      child: const Icon(
        Icons.task_alt_rounded,
        color: NusaColors.success,
        size: 50,
      ),
    ),
  );
}

class _Notice extends StatelessWidget {
  const _Notice({required this.message, this.warning = false});
  final String message;
  final bool warning;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: warning ? const Color(0xFFFFF8D7) : NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(13),
      border: Border.all(
        color: warning
            ? NusaColors.accent.withValues(alpha: 0.7)
            : NusaColors.outline,
      ),
    ),
    child: Text(message, style: const TextStyle(fontSize: 11.5, height: 1.4)),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 52,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: 14),
            FilledButton.tonalIcon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Coba Lagi'),
            ),
          ],
        ],
      ),
    ),
  );
}

enum _AnswerSaveStatus { pending, saving, saved, failed }

bool _hasMedia(StudentExamMedia media) =>
    media.image?.url.isNotEmpty == true ||
    media.table?.rows.isNotEmpty == true ||
    media.formula?.latex.isNotEmpty == true;

String _message(Object error) =>
    error is AppException ? error.message : 'Terjadi gangguan pada ujian.';

String _duration(int seconds) {
  final hours = seconds ~/ 3600;
  final minutes = (seconds % 3600) ~/ 60;
  final remainingSeconds = seconds % 60;
  return [
    hours,
    minutes,
    remainingSeconds,
  ].map((value) => value.toString().padLeft(2, '0')).join(':');
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceAll('.', ',');

extension on String {
  String ifEmpty(String fallback) => isEmpty ? fallback : this;
}
