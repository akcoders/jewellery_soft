import 'package:flutter_test/flutter_test.dart';
import 'package:flutkit/main.dart';

void main() {
  testWidgets('shows login when there is no saved session', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const MyApp(initialSession: null));

    expect(find.text('Aabhushan ERP'), findsOneWidget);
    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
  });
}
