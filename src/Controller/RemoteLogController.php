<?php

namespace Drupal\commerce_vipps\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_vipps\VippsManagerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use zaporylie\Vipps\Model\Payment\ShippingDetails;
use zaporylie\Vipps\Model\Payment\TransactionSummary;
use zaporylie\Vipps\Model\Payment\UserDetails;

/**
 * Returns responses for Commerce Vipps routes.
 */
class RemoteLogController extends ControllerBase {

  /**
   * The commerce_vipps.manager service.
   *
   * @var \Drupal\commerce_vipps\VippsManagerInterface
   */
  protected $commerceVippsManager;

  /**
   * The commerce_price.currency_formatter service.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $commercePriceCurrencyFormatter;

  /**
   * The controller constructor.
   *
   * @param \Drupal\commerce_vipps\VippsManagerInterface $commerce_vipps_manager
   *   The commerce_vipps.manager service.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $commerce_price_currency_formatter
   *   The commerce_price.currency_formatter service.
   */
  public function __construct(VippsManagerInterface $commerce_vipps_manager, CurrencyFormatterInterface $commerce_price_currency_formatter) {
    $this->commerceVippsManager = $commerce_vipps_manager;
    $this->commercePriceCurrencyFormatter = $commerce_price_currency_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_vipps.manager'),
      $container->get('commerce_price.currency_formatter')
    );
  }

  /**
   * Builds the response.
   */
  public function build(PaymentInterface $payment) {

    $paymentManager = $this->commerceVippsManager->getPaymentManager($payment->getPaymentGateway()->getPlugin());
    $details = $paymentManager->getPaymentDetails($payment->getRemoteId());

    // Transaction summary.
    if ($details->getTransactionSummary() instanceof TransactionSummary) {
      $rows = [
        [
          ['data' => $this->t('Captured Amount'), 'header' => TRUE],
          $this->commercePriceCurrencyFormatter->format(Calculator::divide($details->getTransactionSummary()->getCapturedAmount(), 100), 'NOK'),
        ],
        [
          ['data' => $this->t('Refunded Amount'), 'header' => TRUE],
          $this->commercePriceCurrencyFormatter->format(Calculator::divide($details->getTransactionSummary()->getRefundedAmount(), 100), 'NOK'),
        ],
        [
          ['data' => $this->t('Remaining Amount to Capture'), 'header' => TRUE],
          $this->commercePriceCurrencyFormatter->format(Calculator::divide($details->getTransactionSummary()->getRemainingAmountToCapture(), 100), 'NOK'),
        ],
        [
          ['data' => $this->t('Remaining Amount to Refund'), 'header' => TRUE],
          $this->commercePriceCurrencyFormatter->format(Calculator::divide($details->getTransactionSummary()->getRemainingAmountToRefund(), 100), 'NOK'),
        ],
      ];
      $build['transaction_summary'] = [
        '#type' => 'table',
        '#rows' => $rows,
        '#caption' => $this->t('Transaction Summary'),
      ];
    }

    // User details.
    if ($details->getUserDetails() instanceof UserDetails) {
      $rows = [
        [
          ['data' => $this->t('First Name'), 'header' => TRUE],
          $details->getUserDetails()->getFirstName(),
        ],
        [
          ['data' => $this->t('Last Name'), 'header' => TRUE],
          $details->getUserDetails()->getLastName(),
        ],
        [
          ['data' => $this->t('Email'), 'header' => TRUE],
          $details->getUserDetails()->getEmail(),
        ],
        [
          ['data' => $this->t('Phone'), 'header' => TRUE],
          $details->getUserDetails()->getMobileNumber(),
        ],
        [
          ['data' => $this->t('User ID'), 'header' => TRUE],
          $details->getUserDetails()->getUserId(),
        ],
        [
          ['data' => $this->t('Date of birth'), 'header' => TRUE],
          $details->getUserDetails()->getDateOfBirth(),
        ],
        [
          ['data' => $this->t('SSN'), 'header' => TRUE],
          $details->getUserDetails()->getSsn(),
        ],
        [
          ['data' => $this->t('Is BankID verified?'), 'header' => TRUE],
          $details->getUserDetails()
            ->getBankIdVerified() ? $this->t('Yes') : $this->t('No'),
        ],
      ];
      $build['user_details'] = [
        '#type' => 'table',
        '#rows' => $rows,
        '#caption' => $this->t('User details'),
      ];
    }

    // Shipping details.
    if ($details->getShippingDetails() instanceof ShippingDetails) {
      $rows = [
        [
          ['data' => $this->t('Shipping Method'), 'header' => TRUE],
          $details->getShippingDetails()->getShippingMethod() . ' (' . $details->getShippingDetails()->getShippingMethodId() . ')',
        ],
        [
          ['data' => $this->t('shipping Cost'), 'header' => TRUE],
          $details->getShippingDetails()->getShippingCost(),
        ],
        [
          ['data' => $this->t('Shipping Address'), 'header' => TRUE],
          new FormattableMarkup('@line_1 @line2, @post_code @city, @country', [
            '@line_1' => $details->getShippingDetails()->getAddress()->getAddressLine1(),
            '@line_2' => $details->getShippingDetails()->getAddress()->getAddressLine2(),
            '@city' => $details->getShippingDetails()->getAddress()->getCity(),
            '@country' => $details->getShippingDetails()->getAddress()->getCountry(),
            '@post_code' => $details->getShippingDetails()->getAddress()->getPostCode(),
          ]),
        ],
      ];
      $build['shipping_details'] = [
        '#type' => 'table',
        '#rows' => $rows,
        '#caption' => $this->t('Shipping details'),
      ];
    }

    // Transaction log.
    if (is_array($details->getTransactionLogHistory())) {
      $header = [
        $this->t('Operation'),
        $this->t('Operation success'),
        $this->t('Amount'),
        $this->t('Timestamp'),
        $this->t('Transaction text'),
        $this->t('Transaction ID'),
        $this->t('Request ID'),
      ];
      $rows = [];
      foreach ($details->getTransactionLogHistory() as $logEntry) {
        $rows[] = [
          $logEntry->getOperation(),
          $logEntry->getOperationSuccess() ? $this->t('Yes') : $this->t('No'),
          $this->commercePriceCurrencyFormatter->format(Calculator::divide($logEntry->getAmount(), 100), 'NOK'),
          $logEntry->getTimeStamp()->format('c'),
          $logEntry->getTransactionText(),
          $logEntry->getTransactionId(),
          $logEntry->getRequestId(),
        ];
      }
      $build['transaction_log'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#caption' => $this->t('Transaction log'),
      ];
    }

    return $build;
  }

}
