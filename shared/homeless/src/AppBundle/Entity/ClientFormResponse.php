<?php


namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * Заполненная редактируемая форма
 *
* @ORM\Entity(repositoryClass="AppBundle\Repository\ClientFormResponseRepository")
*/
class ClientFormResponse extends BaseEntity
{
    /**
     * Клиент, к которому относится анкета
     *
     * @var Client
     * @ORM\ManyToOne(targetEntity="Client")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Client $client = null;

    /**
     * Форма заполненной анкеты
     *
     * @var ClientForm|null
     * @ORM\ManyToOne(targetEntity="ClientForm")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ClientForm $form = null;

    /**
     * Поля заполненной анкеты
     *
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="ClientFormResponseValue", mappedBy="clientFormResponse",
     *     cascade={"persist", "remove", "detach"},
     *     orphanRemoval=true,
     * )
     */
    private Collection $values;

    /**
     * Ссылка на ResidentQuestionnaire, из которого была скопирована заполненная анкета.
     * Нужна на период миграции из старой формы анкеты в новую.
     *
     * @var integer|null
     * @ORM\Column(type="integer", nullable=true, unique=true)
     */
    private ?int $residentQuestionnaireId = null;

    /**
     * Набор значений полей формы из запроса на создание/обновление заполненной анкеты.
     * Заполняется через магический метод `__set`.
     *
     * Ключ - ID поля, значение - значение поля.
     * Не записывается в базу. Админка перед сохранением заполненной анкеты преобразует массив в набор объектов,
     * который потом будет записан в $values
     *
     * @var array
     */
    private array $_submittedFields = [];

    /**
     * ClientFormResponse constructor.
     */
    public function __construct()
    {
        $this->values = new ArrayCollection();
    }

    /**
     * @return Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return ClientForm
     */
    public function getForm(): ?ClientForm
    {
        return $this->form;
    }

    /**
     * @param ClientForm $form
     */
    public function setForm(ClientForm $form)
    {
        $this->form = $form;
    }

    /**
     * @return ArrayCollection
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param ArrayCollection $values
     */
    public function setValues(Collection $values)
    {
        $this->values = $values;
    }

    /**
     * Магическая функция для чтения значения поля анкеты по её ID.
     * $r->__get('field_2') вернёт значение поля с ID == 2.
     * Все поля получаются через $this->getValues().
     *
     * @param $name
     * @return string|null
     */
    public function __get($name)
    {
        if (substr($name, 0, 6) === 'field_') {
            return $this->getFieldValue(substr($name, 6));
        }
        throw new LogicException("No field $name in ClientFormResponse");
    }

    private function getFieldValue($fieldId): ?string
    {
        foreach ($this->getValues() as $value) {
            /**
             * @var $value ClientFormResponseValue
             */
            if ($value->getClientFormField()->getId() == $fieldId) {
                return $value->getValue();
            }
        }
        return null;
    }

    /**
     * Магический метод для админки. Через него выставляются значения полей формы.
     * Значения полей не будут сами сохранены в БД, а будут прикопаны в массив $this->_submittedFields
     *
     * @see _submittedFields
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (substr($name, 0, 6) === 'field_') {
            $this->_submittedFields[substr($name, 6)] = $value;
            return;
        }
        throw new LogicException("No field $name in ClientFormResponse");
    }

    /**
     * Возвращает массив `$this->_submittedFields`
     *
     * @see _submittedFields
     * @return array
     */
    public function _getSubmittedFields(): array
    {
        return $this->_submittedFields;
    }

    /**
     * Возвращает значение первого поля заполненной формы.
     * Первое поле вычисляется хитрым образом с кешированием, см функцию `getCachedFirstField`.
     *
     * @return string|null
     * @see ClientFormResponse::getCachedFirstField()
     */
    public function getFirstFieldValue(): ?string
    {
        $firstField = $this->getCachedFirstField();
        if ($firstField === null) {
            return null;
        }
        return $this->getFieldValue($firstField->getId());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $value = $this->getFirstFieldValue();
        return $value === null ? '' : $value;
    }

    /**
     * Вычисляет, заполнена ли анкета.
     * Анкета считается заполненной, если в ней заполнено хотя бы одно поле не считая первого.
     * Первое поле вычисляется хитрым образом с кешированием, см функцию `getCachedFirstField`.
     *
     * @return bool
     * @see ClientFormResponse::getCachedFirstField()
     */
    public function isFull(): bool
    {
        $firstField = $this->getCachedFirstField();
        $firstFieldId = null;
        if ($firstField !== null) {
            $firstFieldId = $firstField->getId();
        }

        foreach ($this->getValues() as $value) {
            /**
             * @var $value ClientFormResponseValue
             */
            if ($value->getClientFormField()->getId() !== $firstFieldId && $value->getValue() !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает ID объекта `ResidentQuestionnaire`, из которого была скопирована анкета.
     * Если здесь `null`, значит анкета была создана вручную.
     *
     * @return int|null
     * @see ResidentQuestionnaire
     */
    public function getResidentQuestionnaireId(): ?int
    {
        return $this->residentQuestionnaireId;
    }

    /**
     * @param int|null $residentQuestionnaireId
     */
    public function setResidentQuestionnaireId(?int $residentQuestionnaireId)
    {
        $this->residentQuestionnaireId = $residentQuestionnaireId;
    }

    /**
     * @var ClientFormField|null
     */
    private ?ClientFormField $cachedFirstField = null;

    /**
     * Возвращает закешированное первое поле текущей формы.
     * Первое поле вычисляется по сортировке по значению `sort` полей формы, и кешируется на всё время жизни
     * объекта-анкеты.
     *
     * @return ClientFormField|null
     */
    private function getCachedFirstField(): ?ClientFormField
    {
        if ($this->cachedFirstField !== null) {
            return $this->cachedFirstField;
        }
        $form = $this->getForm();
        if ($form === null) {
            return null;
        }
        $this->cachedFirstField = $form->getFirstField();
        return $this->cachedFirstField;
    }
}
