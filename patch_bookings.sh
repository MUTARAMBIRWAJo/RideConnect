sed -i -e '/->actions(\[/ a\
                Tables\\Actions\\ActionGroup::make([\
                    Tables\\Actions\\Action::make('\''print_receipt\'')\
                        ->label('\''Print Receipt\'')\
                        ->color('\''success\'')\
                        ->icon('\''heroicon-o-printer\'')\
                        ->visible(fn (): bool => auth()->user()?->hasRole(['\''super_admin'\'', '\''admin'\'', '\''officer'\'', '\''accountant'\'']) ?? false)\
                        ->action(function (Booking $record) {\
                            $pdf = app(\\App\\Services\\BookingReceiptDeliveryService::class)->generatePdfBinary($record);\
                            return response()->streamDownload(fn () => print($pdf), "booking-receipt-{$record->id}.pdf");\
                        }),\
\
                    Tables\\Actions\\Action::make('\''email_receipt\'')\
                        ->label('\''Email Receipt\'')\
                        ->icon('\''heroicon-o-envelope\'')\
                        ->color('\''info\'')\
                        ->visible(fn (): bool => auth()->user()?->hasRole(['\''super_admin'\'', '\''admin'\'', '\''officer'\'', '\''accountant'\'']) ?? false)\
                        ->action(function (Booking $record) {\
                            try {\
                                app(\\App\\Services\\BookingReceiptDeliveryService::class)->sendToEmail($record);\
                                \\Filament\\Notifications\\Notification::make()->title('\''Email sent successfully\'')->success()->send();\
                            } catch (\\Exception $e) {\
                                \\Filament\\Notifications\\Notification::make()->title('\''Failed to send email\'')->body($e->getMessage())->danger()->send();\
                            }\
                        }),\
\
                    Tables\\Actions\\Action::make('\''whatsapp_receipt\'')\
                        ->label('\''WhatsApp Receipt\'')\
                        ->icon('\''heroicon-o-chat-bubble-left-right\'')\
                        ->color('\''success\'')\
                        ->visible(fn (): bool => auth()->user()?->hasRole(['\''super_admin'\'', '\''admin'\'', '\''officer'\'', '\''accountant'\'']) ?? false)\
                        ->action(function (Booking $record) {\
                            try {\
                                app(\\App\\Services\\BookingReceiptDeliveryService::class)->sendToWhatsApp($record);\
                                \\Filament\\Notifications\\Notification::make()->title('\''WhatsApp message sent successfully\'')->success()->send();\
                            } catch (\\Exception $e) {\
                                \\Filament\\Notifications\\Notification::make()->title('\''Failed to send WhatsApp\'')->body($e->getMessage())->danger()->send();\
                            }\
                        }),\
\
                    Tables\\Actions\\Action::make('\''sms_receipt\'')\
                        ->label('\''SMS Receipt\'')\
                        ->icon('\''heroicon-o-device-phone-mobile\'')\
                        ->color('\''primary\'')\
                        ->visible(fn (): bool => auth()->user()?->hasRole(['\''super_admin'\'', '\''admin'\'', '\''officer'\'', '\''accountant'\'']) ?? false)\
                        ->action(function (Booking $record) {\
                            try {\
                                app(\\App\\Services\\BookingReceiptDeliveryService::class)->sendToSms($record);\
                                \\Filament\\Notifications\\Notification::make()->title('\''SMS sent successfully\'')->success()->send();\
                            } catch (\\Exception $e) {\
                                \\Filament\\Notifications\\Notification::make()->title('\''Failed to send SMS\'')->body($e->getMessage())->danger()->send();\
                            }\
                        }),\
                ])->label('\''Receipt\'')->icon('\''heroicon-o-document-text\''),
' app/Filament/Resources/BookingResource.php
