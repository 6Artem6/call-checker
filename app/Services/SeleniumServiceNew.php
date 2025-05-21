<?php

namespace App\Services;

use App\Models\AccountOAuth2;
use App\Models\UserCookie;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Класс для работы с Selenium WebDriver
 * Используется для автоматизации действий в браузере
 */
class SeleniumServiceNew
{
    /**
     * Экземпляр драйвера Selenium
     */
    protected RemoteWebDriver $driver;

    /**
     * Базовый URL сайта
     */
    protected string $baseUrl;

    /**
     * Имя пользователя для авторизации
     */
    protected string $username;

    /**
     * Пароль для авторизации
     */
    protected string $password;

    /**
     * Таймаут ожидания элементов (в секундах)
     */
    protected int $timeout = 10;

    /**
     * Селекторы для элементов интерфейса
     */
    private static function getSelectors() {
        return [
            'LOGIN' => [
                'USERNAME_FIELD' => WebDriverBy::name('username'),
                'PASSWORD_FIELD' => WebDriverBy::name('password'),
                'SUBMIT_BUTTON' => WebDriverBy::cssSelector('button[type="submit"]'),
                'AUTH_FORM' => WebDriverBy::id('authentication'),
            ],
            'LEAD' => [
                'CHAT_TAB' => 'data-id="chat"', // Для JS-селектора
                'USER_NAME' => '.feed-compose-user__name', // Для JS-селектора
                'SUGGEST_ITEM' => '.multisuggest__suggest-item', // Для JS-селектора
                'MESSAGE_FIELD' => WebDriverBy::className('feed-compose__message'),
                'SEND_BUTTON' => WebDriverBy::className('feed-note__button'),
            ],
        ];
    }

    /**
     * Создаёт экземпляр сервиса Selenium
     *
     * @throws Exception В случае ошибки инициализации драйвера
     */
    public function __construct()
    {
        Log::info("Инициализация SeleniumService");

        try {
            $host = env('SELENIUM_API_URL');

            $options = (new ChromeOptions())->addArguments([
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--remote-debugging-port=0',
                '--no-sandbox',
                '--headless'
            ]);

            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

            $sessionId = Cache::get('webdriver_session');

            try {
                if ($sessionId) {
                    $this->driver = RemoteWebDriver::createBySessionID($sessionId, $host, 5000);
                    Log::info("Повторно использован существующий сеанс WebDriver");
                } else {
                    throw new Exception("Сессия не найдена, создаём новую");
                }
            } catch (Exception $e) {
                Log::info("Создание нового сеанса WebDriver: " . $e->getMessage());
                $this->driver = RemoteWebDriver::create($host, $capabilities, 5000);
                Cache::put('webdriver_session', $this->driver->getSessionID(), 600);
            }

            Log::info("SeleniumService инициализирован успешно");
        } catch (Exception $e) {
            Log::error("Ошибка при инициализации SeleniumService: " . $e->getMessage());
            throw new Exception("Не удалось инициализировать Selenium WebDriver: " . $e->getMessage());
        }
    }

    /**
     * Устанавливает учетные данные для авторизации
     *
     * @param string $username Имя пользователя
     * @param string $password Пароль
     * @return self
     */
    public function setCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Устанавливает базовый URL
     *
     * @param string $url Базовый URL
     * @return self
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * Получает куки пользователя из базы данных
     *
     * @param int $userId ID пользователя
     * @return \Illuminate\Database\Eloquent\Collection Коллекция куки
     */
    private function getCookies(int $userId)
    {
        return UserCookie::where('user_id', $userId)->get();
    }

    /**
     * Применяет куки к текущей сессии браузера
     *
     * @param \Illuminate\Database\Eloquent\Collection $cookies Коллекция куки
     * @return void
     */
    private function applyCookies($cookies): void
    {
        try {
            foreach ($cookies as $cookie) {
                $cookieArray = $cookie->toArray();
                // Проверяем обязательные поля для создания cookie
                if (isset($cookieArray['name'], $cookieArray['value'])) {
                    $this->driver->manage()
                        ->addCookie(Cookie::createFromArray($cookieArray));
                }
            }

            // Используем явное ожидание вместо фиксированного sleep
            $this->waitForPageLoad();
            $this->driver->navigate()->refresh();
            $this->waitForPageLoad();
        } catch (Exception $e) {
            Log::error("Ошибка при применении cookies: " . $e->getMessage());
        }
    }

    /**
     * Авторизация с помощью токена
     *
     * @param int $userId ID пользователя
     * @return array Статус авторизации
     */
    public function loginWithToken(int $userId): array
    {
        try {
            $cookies = $this->getCookies($userId);
            if ($cookies->isEmpty()) {
                Log::info("Куки не найдены для пользователя $userId, выполняем обычную авторизацию");
                return $this->login($userId);
            }

            $this->driver->get($this->baseUrl);
            $this->waitForPageLoad();
            $this->applyCookies($cookies);

            // Проверяем, авторизован ли пользователь
            $wait = new WebDriverWait($this->driver, 5);
            try {
                $wait->until(function ($driver) {
                    return empty($driver->findElements(static::getSelectors()['LOGIN']['AUTH_FORM']));
                });
                Log::info("Успешная авторизация с токеном для пользователя $userId");
                return ['status' => 'logged_in_with_token'];
            } catch (Exception) {
                Log::info("Авторизация с токеном не удалась для пользователя $userId, выполняем обычную авторизацию");
                return $this->login($userId);
            }
        } catch (Exception $e) {
            Log::error("Ошибка при авторизации с токеном: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Авторизация с использованием куки
     *
     * @param int $userId ID пользователя
     * @return array Статус авторизации
     */
    public function loginWithCookies(int $userId): array
    {
        try {
            $cookies = $this->getCookies($userId);
            if ($cookies->isEmpty()) {
                Log::info("Куки не найдены для пользователя $userId, выполняем обычную авторизацию");
                return $this->login($userId);
            }

            $this->driver->get($this->baseUrl);
            $this->waitForPageLoad();
            $this->applyCookies($cookies);

            // Проверяем, авторизован ли пользователь
            $wait = new WebDriverWait($this->driver, 5);
            try {
                $wait->until(function ($driver) {
                    return empty($driver->findElements(static::getSelectors()['LOGIN']['AUTH_FORM']));
                });
                Log::info("Успешная авторизация с куки для пользователя $userId");
                return ['status' => 'logged_in_with_cookies'];
            } catch (Exception) {
                Log::info("Авторизация с куки не удалась для пользователя $userId, выполняем обычную авторизацию");
                return $this->login($userId);
            }
        } catch (Exception $e) {
            Log::error("Ошибка при авторизации с куки: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Авторизация пользователя
     *
     * @param int $userId ID пользователя
     * @return array Статус авторизации
     */
    private function login(int $userId): array
    {
        Log::info("Выполняем авторизацию пользователя $userId");

        try {
            $this->driver->get($this->baseUrl);
            $this->waitForPageLoad();

            // Ожидаем загрузку формы авторизации
            $wait = new WebDriverWait($this->driver, $this->timeout);
            $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(static::getSelectors()['LOGIN']['USERNAME_FIELD'])
            );

            // Заполняем форму и авторизуемся
            $this->driver->findElement(static::getSelectors()['LOGIN']['USERNAME_FIELD'])->sendKeys($this->username);
            $this->driver->findElement(static::getSelectors()['LOGIN']['PASSWORD_FIELD'])->sendKeys($this->password);
            $this->driver->findElement(static::getSelectors()['LOGIN']['SUBMIT_BUTTON'])->click();

            // Ожидаем завершения авторизации
            $this->waitForPageLoad();

            // Сохраняем куки
            $this->saveCookies($userId);

            Log::info("Успешная авторизация пользователя $userId");
            return ['status' => 'logged_in'];
        } catch (Exception $e) {
            Log::error("Ошибка при авторизации: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Сохраняет куки текущей сессии браузера для пользователя
     *
     * @param int $userId ID пользователя
     * @return void
     */
    private function saveCookies(int $userId): void
    {
        try {
            foreach ($this->driver->manage()->getCookies() as $cookie) {
                UserCookie::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'name' => $cookie->getName()
                    ],
                    $cookie->toArray()
                );
            }
            Log::info("Куки успешно сохранены для пользователя $userId");
        } catch (Exception $e) {
            Log::error("Ошибка при сохранении куки: " . $e->getMessage());
        }
    }

    /**
     * Ожидает загрузку страницы
     *
     * @param int|null $timeout Таймаут ожидания в секундах (если null, используется значение по умолчанию)
     * @return void
     */
    private function waitForPageLoad(?int $timeout = null): void
    {
        $timeout = $timeout ?? $this->timeout;
        $this->driver->wait($timeout)->until(
            function ($driver) {
                return $driver->executeScript('return document.readyState') === 'complete';
            }
        );
    }

    /**
     * Ожидает, пока элемент станет кликабельным
     *
     * @param WebDriverBy $selector Селектор элемента
     * @param int|null $timeout Таймаут ожидания в секундах (если null, используется значение по умолчанию)
     * @return void
     */
    private function waitForElementClickable(WebDriverBy $selector, ?int $timeout = null): void
    {
        $timeout = $timeout ?? $this->timeout;
        $wait = new WebDriverWait($this->driver, $timeout);
        $wait->until(
            WebDriverExpectedCondition::elementToBeClickable($selector)
        );
    }

    /**
     * Отправляет сообщение лиду
     *
     * @param int $lead_id ID лида
     * @param string $text Текст сообщения
     * @return bool Успешность отправки
     */
    public function sendLeadMessage(int $lead_id, string $text): bool
    {
        try {
            Log::info("Отправка сообщения лиду $lead_id: $text");
            Log::info("1 {$this->driver->getCurrentURL()}");

            // Переходим на страницу деталей лида
            $this->driver->get("{$this->baseUrl}/leads/detail/{$lead_id}");

            // Ожидаем загрузку страницы и URL, содержащий leads/detail
            $this->waitForPageLoad();
            $wait = new WebDriverWait($this->driver, $this->timeout);
            $wait->until(
                WebDriverExpectedCondition::urlContains('leads/detail')
            );
            Log::info("2 {$this->driver->getCurrentURL()}");

            sleep(5);
            $this->driver->executeScript("$('[data-id=\"chat\"]').click()");
            sleep(3);
            $this->driver->executeScript("$('.feed-compose-user__name').click()");
            sleep(3);
            $this->driver->executeScript("$('.multisuggest__suggest-item')[0].click()");
            sleep(3);
            $this->driver->findElement(WebDriverBy::className('feed-compose__message'))->sendKeys($text);
            sleep(1);
            $this->driver->findElement(WebDriverBy::className('feed-note__button'))->click();

           /* $this->clickElementSafely('.feed-compose-switcher', true, 5);

            // Переключаемся на вкладку чата - используем более надежный метод с ожиданием
            $this->clickElementSafely(static::getSelectors()['LEAD']['CHAT_TAB'], true, 5);

            // Открываем выбор пользователя
            $this->clickElementSafely(static::getSelectors()['LEAD']['USER_NAME'], true, 5);

            // Выбираем первого пользователя из списка
            $this->clickElementSafely(static::getSelectors()['LEAD']['SUGGEST_ITEM'] . '[0]', true, 10);

            // Вводим текст сообщения и отправляем
            $this->waitForElementClickable(static::getSelectors()['LEAD']['MESSAGE_FIELD'], 5);
            $this->driver->findElement(static::getSelectors()['LEAD']['MESSAGE_FIELD'])->sendKeys($text);

            $this->waitForElementClickable(static::getSelectors()['LEAD']['SEND_BUTTON'], 5);
            $this->driver->findElement(static::getSelectors()['LEAD']['SEND_BUTTON'])->click();*/

            Log::info("Сообщение успешно отправлено лиду $lead_id");
            return true;
        } catch (Exception $e) {
            Log::error("Ошибка при отправке сообщения лиду $lead_id: " . $e->getMessage() . " - " .
                $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Безопасный клик по элементу с поддержкой селекторов JavaScript
     *
     * @param string $selector Селектор элемента (CSS или JavaScript)
     * @param bool $isJsSelector Использовать ли JavaScript для поиска элемента
     * @param int $waitSeconds Время ожидания после клика в секундах
     * @return bool Успешность клика
     */
    private function clickElementSafely(string $selector, bool $isJsSelector = false, int $waitSeconds = 2): bool
    {
        try {
            if ($isJsSelector) {
                // Для JS-селекторов - jQuery или чистый JS
                if (str_contains($selector, '[')) {
                    // Если селектор содержит индекс ([0]), используем его как индекс массива
                    $parts = explode('[', $selector);
                    $baseSelector = $parts[0];
                    $index = rtrim($parts[1], ']');

                    $script = "
                        var elements = document.querySelectorAll('$baseSelector');
                        if (elements.length > $index) {
                            elements[$index].click();
                        }
                    ";
                } else if (str_contains($selector, 'data-id=')) {
                    // Селектор с data-атрибутом
                    $attr = str_replace('"', '', $selector);
                    $script = "
                        var element = document.querySelector('[" . $attr . "]');
                        if (element) {
                            element.click();
                        }
                    ";
                } else {
                    // Обычный CSS-селектор
                    $script = "
                        var element = document.querySelector('$selector');
                        if (element) {
                            element.click();
                        }
                    ";
                }

                $result = $this->driver->executeScript($script);

                if ($result !== true) {
                    Log::warning("Элемент не найден или не кликабелен: $selector - $script");
                    return false;
                }
            } else {
                // Обычный WebDriver селектор
                $by = WebDriverBy::cssSelector($selector);
                $this->waitForElementClickable($by);
                $this->driver->findElement($by)->click();
            }

            // Ожидание после клика
            if ($waitSeconds > 0) {
                sleep($waitSeconds);
            }

            return true;
        } catch (Exception $e) {
            Log::error("Ошибка при клике на элемент $selector: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Закрывает сеанс драйвера
     *
     * @return void
     */
    public function __destruct()
    {
        try {
            // Не закрываем драйвер, так как он может быть использован в других запросах
            // Сессия кэшируется и будет закрыта автоматически
            Log::info("SeleniumService завершает работу");
        } catch (Exception $e) {
            Log::error("Ошибка при завершении работы SeleniumService: " . $e->getMessage());
        }
    }
}