import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/survey_statement/domain/survey_statement.dart';

class SurveyStatementFormSheet extends StatefulWidget {
  const SurveyStatementFormSheet({
    required this.nextOrder,
    this.existing,
    super.key,
  });

  final int nextOrder;
  final SurveyStatement? existing;

  @override
  State<SurveyStatementFormSheet> createState() =>
      _SurveyStatementFormSheetState();
}

class _SurveyStatementFormSheetState extends State<SurveyStatementFormSheet> {
  late final TextEditingController _statementController;
  late final TextEditingController _orderController;
  late bool _active;
  String? _error;

  bool get _editing => widget.existing != null;

  @override
  void initState() {
    super.initState();
    _statementController = TextEditingController(
      text: widget.existing?.statement,
    );
    _orderController = TextEditingController(
      text: '${widget.existing?.order ?? widget.nextOrder}',
    );
    _active = widget.existing?.active ?? true;
  }

  @override
  void dispose() {
    _statementController.dispose();
    _orderController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedPadding(
    duration: const Duration(milliseconds: 160),
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: SizedBox(
      height: (MediaQuery.sizeOf(context).height * 0.72).clamp(420.0, 650.0),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    _editing ? 'Ubah Pernyataan' : 'Tambah Pernyataan',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('close-survey-statement-form'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              key: const Key('survey-statement-form-scroll'),
              padding: const EdgeInsets.all(16),
              children: [
                TextField(
                  key: const Key('survey-statement-form-text'),
                  controller: _statementController,
                  minLines: 3,
                  maxLines: 6,
                  maxLength: 500,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'Pernyataan survei',
                    hintText: 'Contoh: Guru menjelaskan materi dengan jelas.',
                    alignLabelWithHint: true,
                    prefixIcon: Icon(Icons.ballot_outlined),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  key: const Key('survey-statement-form-order'),
                  controller: _orderController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Urutan tampil',
                    prefixIcon: Icon(Icons.format_list_numbered_rounded),
                  ),
                ),
                if (!_editing) ...[
                  const SizedBox(height: 5),
                  SwitchListTile.adaptive(
                    key: const Key('survey-statement-form-active'),
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Langsung aktif'),
                    subtitle: const Text(
                      'Pernyataan aktif akan muncul pada survei siswa berikutnya.',
                    ),
                    value: _active,
                    onChanged: (value) => setState(() => _active = value),
                  ),
                ],
                if (_editing) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          Icons.history_rounded,
                          color: NusaColors.primary,
                          size: 20,
                        ),
                        SizedBox(width: 9),
                        Expanded(
                          child: Text(
                            'Perubahan hanya berlaku pada survei berikutnya. '
                            'Jawaban lama tetap memakai teks sebelumnya.',
                            style: TextStyle(fontSize: 11.5),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-survey-statement'),
                onPressed: _submit,
                icon: const Icon(Icons.save_outlined),
                label: Text(
                  _editing ? 'Simpan Perubahan' : 'Simpan Pernyataan',
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  void _submit() {
    final statement = _statementController.text.trim();
    final order = int.tryParse(_orderController.text.trim());
    if (statement.isEmpty) {
      setState(() => _error = 'Pernyataan survei wajib diisi.');
      return;
    }
    if (order == null || order < 1 || order > 999) {
      setState(() => _error = 'Urutan harus berupa angka 1 sampai 999.');
      return;
    }

    Navigator.pop(
      context,
      SurveyStatementFormValue(
        statement: statement,
        order: order,
        active: _active,
      ),
    );
  }
}
