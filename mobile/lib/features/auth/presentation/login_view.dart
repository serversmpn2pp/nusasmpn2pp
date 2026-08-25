import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_illustrations.dart';
import 'package:nusa/shared/widgets/nusa_logo.dart';

class LoginView extends ConsumerStatefulWidget {
  const LoginView({super.key});

  @override
  ConsumerState<LoginView> createState() => _LoginViewState();
}

class _LoginViewState extends ConsumerState<LoginView> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();
    await ref
        .read(authControllerProvider.notifier)
        .login(
          username: _usernameController.text,
          password: _passwordController.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider).value;
    final isSubmitting = auth?.isSubmitting ?? false;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxHeight < 680;

            return SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(24, compact ? 16 : 28, 24, 0),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 440),
                  child: AutofillGroup(
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Align(
                            alignment: Alignment.center,
                            child: NusaBrand(logoSize: compact ? 64 : 76),
                          ),
                          SizedBox(height: compact ? 14 : 24),
                          Text(
                            'Selamat Datang',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(
                                  color: NusaColors.textPrimary,
                                  fontWeight: FontWeight.w800,
                                ),
                          ),
                          const SizedBox(height: 5),
                          const Text(
                            'Silakan masuk untuk melanjutkan',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 14,
                            ),
                          ),
                          SizedBox(height: compact ? 10 : 16),
                          SizedBox(
                            height: compact ? 70 : 96,
                            child: const NusaSchoolIllustration(opacity: 0.62),
                          ),
                          if (auth?.errorMessage case final message?) ...[
                            const SizedBox(height: 12),
                            _ErrorBanner(message: message),
                          ],
                          const SizedBox(height: 16),
                          NusaTextField(
                            fieldKey: const Key('login-username'),
                            controller: _usernameController,
                            enabled: !isSubmitting,
                            hintText: 'NIP / NISN / ORT-NISN',
                            prefixIcon: Icons.person_outline_rounded,
                            autofillHints: const [AutofillHints.username],
                            textInputAction: TextInputAction.next,
                            errorText: auth?.fieldError('username'),
                            validator: (value) =>
                                value == null || value.trim().isEmpty
                                ? 'NIP, NISN, atau ORT-NISN wajib diisi.'
                                : null,
                          ),
                          const SizedBox(height: 14),
                          NusaTextField(
                            fieldKey: const Key('login-password'),
                            controller: _passwordController,
                            enabled: !isSubmitting,
                            hintText: 'Kata Sandi',
                            prefixIcon: Icons.lock_outline_rounded,
                            obscureText: _obscurePassword,
                            autofillHints: const [AutofillHints.password],
                            textInputAction: TextInputAction.done,
                            onFieldSubmitted: (_) => _submit(),
                            suffixIcon: IconButton(
                              onPressed: () => setState(
                                () => _obscurePassword = !_obscurePassword,
                              ),
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                              tooltip: _obscurePassword
                                  ? 'Tampilkan kata sandi'
                                  : 'Sembunyikan kata sandi',
                            ),
                            validator: (value) => value == null || value.isEmpty
                                ? 'Kata sandi wajib diisi.'
                                : null,
                          ),
                          const SizedBox(height: 20),
                          NusaPrimaryButton(
                            key: const Key('login-submit'),
                            label: 'Masuk',
                            loading: isSubmitting,
                            onPressed: isSubmitting ? null : _submit,
                          ),
                          const SizedBox(height: 12),
                          const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                Icons.info_outline_rounded,
                                size: 16,
                                color: NusaColors.textSecondary,
                              ),
                              SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                  'Lupa kata sandi? Silakan hubungi administrator sekolah.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: NusaColors.textSecondary,
                                    fontSize: 12,
                                    height: 1.35,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          SizedBox(height: compact ? 8 : 12),
                          SizedBox(
                            height: compact ? 82 : 120,
                            child: const NusaEducationIllustration(),
                          ),
                          const SizedBox(height: 10),
                          const Text(
                            'Tim Teknisi SMP Negeri 2 Padang Panjang',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.errorContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(message, style: TextStyle(color: colors.onErrorContainer)),
    );
  }
}
