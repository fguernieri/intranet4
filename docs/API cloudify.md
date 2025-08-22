# Documentação da API – Estrutura Consolidada

## Dados de acesso
AccKey: bjJ4FQvmuA6AoHNDjsDU6893bastards
TokenKey: BHzQaSYPd0dFZPuGQzzR2vnCjwTTfH16wghmEps9HLIHq3Lbnpr6893870e56ec8
LoginUsr: integracaoapis@bastards.com
NomeUsr: INTEGRACAO API BASTARDS

## CC870

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Data início |       <DataInicio> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data fim |       <DataFim> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Número do cupom |       <NrCupom> | [1..1] | Numerico | nan | nan |
| nan | Nr. Caixa |       <NrCaixa> | [1..1] | Numerico | nan | nan |
| nan | Cód. Ref. Produtp |       <CodRefProduto> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. Grupo de Produto |       <CodGrupoProduto> | [1..1] | Numerico | nan | nan |
| nan | Código do cliente |       <CodCliente> | [1..1] | Numerico | nan | nan |
| nan | CPF/CNPJ do cliente |       <CPFCNPJCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | Código do operador de caixa |       <CodOperadorCaixa> | [1..1] | Numerico | nan | nan |
| nan | Código do atendente |       <CodAtendente> | [1..1] | Numerico | nan | nan |
| nan | Código da forma de pagamento |       <CodFormaPagamento> | [1..1] | Numerico | nan | nan |
| nan | Situação do cupom |       <SituacaoCupom> | [1..1] | Numerico | nan | nan |
| nan | Identificador de desconto no cupom |       <IdentifDesconto> | [1..1] | Numerico | nan | nan |
| nan | Identificador de taxa de serviço |       <IdentifTaxaServico> | [1..1] | Numerico | nan | nan |
| nan | Identificador para consultar produtos |       <IdentifConsultaProd> | [1..1] | Numerico | nan | nan |
| nan | Identificador para consultar forma de pagamento |       <IdentifConsultaFormaPagto> | [1..1] | Numerico | nan | nan |
| nan | Identificador para consultar produtos cancelados |       <IdentifConsultaProdCancelados> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC870> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC870Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Qtd. de clientes registrados na portaria |     <ClientesRegistradosPortaria> | [1..1] | Númerico | nan | nan |
| nan | Grupo dados cupom de venda |     <CuponsVenda> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do caixa |         <NrCaixa> | [1..1] | Numerico | nan | nan |
| nan | Descrição do caixa |         <Caixa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do cupom |         <NrCupom> | [1..1] | Numerico | nan | nan |
| nan | Data do movimento |         <DataMovimento> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Número do movimento |         <NrMovimento> | [1..1] | Numerico | nan | nan |
| nan | Hora de abertura |         <HrAbertura> | [1..1] | Numerico | HHNN | nan |
| nan | Hora do fechamento |         <HrFechamento> | [1..1] | Numerico | HHNN | nan |
| nan | Quantidade total de itens |         <QtdeItens> | [1..1] | Numerico | nan | nan |
| nan | Quantidade distinta de produtos |         <QtdeProdutos> | [1..1] | Numerico | nan | nan |
| nan | Sub total dos produtos |         <VlrSubTotalProdutos> | [1..1] | Numerico | nan | nan |
| nan | Descontos do cupom |         <VlrDescontos> | [1..1] | Numerico | nan | nan |
| nan | Acréscimos do cupom |         <VlrAcrescimos> | [1..1] | Numerico | nan | nan |
| nan | Total do cupom |         <VlrTotal> | [1..1] | Numerico | nan | nan |
| nan | Total pago |         <VlrPago> | [1..1] | Numerico | nan | nan |
| nan | Tipo de venda |         <TpVenda> | [1..1] | Numerico | nan | nan |
| nan | Código do cliente - venda a prazo |         <NrCliente> | [1..1] | Numerico | nan | nan |
| nan | Nome do cliente |         <NomeCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | CPF/CNPJ do cliente |         <CPFCNPJCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | Situação do recebimento de venda a prazo |         <SitVendaPrazo> | [1..1] | Numerico | nan | nan |
| nan | Qtd. De clientes atendidos no cupom |         <QtdeClientes> | [1..1] | Numerico | nan | nan |
| nan | Observação |         <Observacoes> | [1..1] | Alfanumerico | nan | nan |
| nan | Chave da NFCe / Nfe |         <ChaveNFCeNFe> | [1..1] | Alfanumerico | nan | nan |
| nan | Série da NFCe / Nfe |         <SerieNFCeNFe> | [1..1] | Numerico | nan | nan |
| nan | Nr. da NFCe / Nfe |         <NrNFCeNFe> | [1..1] | Numerico | nan | nan |
| nan | Situação do cupom |         <SituacaoCupom> | [1..1] | Numerico | nan | nan |
| nan | Descrição da situação |         <DescSituacao> | [1..1] | Alfanumerico | nan | nan |
| nan | Motivo do cancelamento |         <MotivoCancelamentoCupom> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. usuario que cancelou o cupom |         <CodUsuarioCancelamento> | [1..1] | Numerico | nan | nan |
| nan | Nome usuario que cancelou o cupom |         <NomeUsuarioCancelamento> | [1..1] | Alfanumerico | nan | nan |
| nan | Setor |         <Setor> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados pagamentos |         <Pagamentos> | [1..n] | Grupo | nan | nan |
| nan | Sequencial do pagamento |             <NrSeqPagto> | [1..1] | Numerico | nan | nan |
| nan | Tipo de pagamento |             <TipoPagto> | [1..1] | Numerico | nan | nan |
| nan | Numero de parcelas |             <NrParcelas> | [1..1] | Numerico | nan | nan |
| nan | Descrição do meio de pagamento |             <DescFormaPagamento> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. forma pagamento |             <CodFormaPagamento> | [1..1] | Numerico | nan | nan |
| nan | Valor pago |             <VlrPago> | [1..1] | Numerico | nan | nan |
| nan | Data do pagamento |             <DataPagto> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Nr. da adquirente |             <NrAdiquirente> | [1..1] | Numerico | nan | nan |
| nan | Nome da rede adquirente |             <NomeAdiquirente> | [1..1] | Alfanumerico | nan | nan |
| nan | Código do produto de cartão |             <CodProdutoCartao> | [1..1] | Numerico | nan | nan |
| nan | Nome do produto de cartão |             <NomeProdutoCartao> | [1..1] | Alfanumerico | nan | nan |
| nan | Taxa cobrada pela adquirente |             <TaxaAdquirente> | [1..1] | Numerico | nan | nan |
| nan | Valor da tarifa da taxa de transação |             <VlrTarifaTaxa> | [1..1] | Numerico | nan | nan |
| nan | Valor da tarifa fixa da transação |             <VlrTarifaFixa> | [1..1] | Numerico | nan | nan |
| nan | Valor total da tarifa |             <VlrTarifaTotal> | [1..1] | Numerico | nan | nan |
| nan | Data de corte |             <DataCorte> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data prevista para recebimento |             <DataPrevRecebimento> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Prazo de recebimento (dias) |             <PrazoRecebimento> | [1..1] | Numerico | nan | nan |
| nan | Numero da transação TEF |             <NrTransacaoTef> | [1..1] | Alfanumerico | nan | nan |
| nan | Numero da transação TEF host |             <NrTransacaoTefHost> | [1..1] | Alfanumerico | nan | nan |
| nan | Código de autorizacao |             <CodigoAutorizacao> | [1..1] | Alfanumerico | nan | nan |
| nan | Data e hora da transação |             <DataHoraAutorizacao> | [1..1] | Alfanumerico | AAAAMMDDHHMMSS | nan |
| nan | Numero do cartão do cliente |             <NrCartao> | [1..1] | Alfanumerico | nan | nan |
| nan | Situação TEF |             <SitTEF> | [1..1] | Numerico | nan | nan |
| nan | Fim grupo dados pagamentos |         <Pagamentos> | [1..n] | Grupo | nan | nan |
| nan | Grupo dados produtos |         <Produtos> | [1..n] | Grupo | nan | nan |
| nan | Número do item |             <NrItem> | [1..1] | Numerico | nan | nan |
| nan | Código do produto |             <CodProduto> | [1..1] | Numerico | nan | nan |
| nan | Código de referência do produto |             <CodRefProduto> | [1..1] | Alfanumerico | nan | nan |
| nan | Código de barras |             <CodBarras> | [1..1] | AlfaNumerico | nan | nan |
| nan | Descrição do produto |             <DescProduto> | [1..1] | AlfaNumerico | nan | nan |
| nan | Cód. Grupo de produto |             <CodGrupo> | [1..1] | Numerico | nan | nan |
| nan | Grupo de produto |             <DescGrupo> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. unidade medida |             <Unidade> | [1..1] | AlfaNumerico | nan | nan |
| nan | Valor de venda do produto |             <VlrUnitario> | [1..1] | Numerico | nan | nan |
| nan | Quantidade do produto |             <Qtde> | [1..1] | Numerico | nan | nan |
| nan | Sub total |             <SubTotal> | [1..1] | Numerico | nan | nan |
| nan | Numero da promoção |             <NrPromocao> | [1..1] | Numerico | nan | nan |
| nan | Descrição da promoção |             <DescPromocao> | [1..1] | AlfaNumerico | nan | nan |
| nan | Valor dos acréscimos |             <VlrAcrescimos> | [1..1] | Numerico | nan | nan |
| nan | Código do tipo de desconto |             <CodTipoDesconto> | [1..1] | Numerico | nan | nan |
| nan | Descrição do desconto |             <DescTipoDesconto> | [1..1] | AlfaNumerico | nan | nan |
| nan | Valor dos descontos |             <VlrDescontos> | [1..1] | Numerico | nan | nan |
| nan | Cod. usuario que autorizou/aplicou o desconto |             <CodUsuarioDesconto> | [1..1] | Numerico | nan | nan |
| nan | Nome usuario que autorizou/aplicou o desconto |             <NomeUsuarioDesconto> | [1..1] | AlfaNumerico | nan | nan |
| nan | Total do item |             <VlrTotal> | [1..1] | Numerico | nan | nan |
| nan | Valor do desconto rateado da venda |             <VlrDescCupomRateio> | [1..1] | Numerico | nan | nan |
| nan | Total líquido do item no cupom |             <VlrTotalLiq> | [1..1] | Numerico | nan | nan |
| nan | Código do usuário vendedor |             <CodUsuarioVendedor> | [1..1] | Numerico | nan | nan |
| nan | Nome do usuário vendedor |             <NomeUsuarioVendedor> | [1..1] | AlfaNumerico | nan | nan |
| nan | Nr. da mesa ou comanda |             <NrMesaComanda> | [1..1] | Numerico | nan | nan |
| nan | Data/hora de lançamento na comanda |             <DataHoraLancamento> | [1..1] | AlfaNumerico | AAAAMMDDHHMMSS | nan |
| nan | Código do usuário resp. estorno/exclusão |             <CodUsuarioExclusao> | [1..1] | Numerico | nan | nan |
| nan | Nome do usuário resp. estorno/exclusão |             <NomeUsuarioExclusao> | [1..1] | AlfaNumerico | nan | nan |
| nan | Motivo do estono/cancelamento |             <MotivoExclusao> | [1..1] | AlfaNumerico | nan | nan |
| nan | Situação do item |             <SituacaoItem> | [1..1] | Numerico | nan | nan |
| nan | Descrição da situação do item |             <DescSituacaoItem> | [1..1] | Alfanumerico | nan | nan |
| nan | Fim do grupo de dados produtos |         </Produtos> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo de dados cupom de venda |     </CuponsVenda> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC871

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Tipo de data a aplicaro o filtro |       <TpData> | [1..1] | Numerico | nan | nan |
| nan | Data início |       <DataInicio> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data fim |       <DataFim> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Situação do título |       <SituacaoTitulo> | [1..1] | Numerico | nan | nan |
| nan | Nr. Conta |       <NrConta> | [1..1] | Numerico | nan | nan |
| nan | Nr. Categoria |       <NrCategoria> | [1..1] | Numerico | nan | nan |
| nan | Identificador para consultar contas a pagar |       <ConsultaContasPagar> | [1..1] | Numerico | nan | nan |
| nan | Identificador para consultar contas a receber |       <ConsultaContasReceber> | [1..1] | Numerico | nan | nan |
| nan | Identificador para consultar transferências |       <ConsultaTransferencias> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC871> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC871Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados títulos |     <Titulos> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Tipo de lançamento |         <TipoLancamento> | [1..1] | Numerico | nan | nan |
| nan | Conta bancária |         <ContaBancaria> | [1..1] | Alfanumerico | nan | nan |
| nan | Data |         <Data> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data de vencimento |         <DataVencimento> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data de pagamento |         <DataPagto> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data do documento |         <DataDocumento> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data de lançamento |         <DataLancamento> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Descrição do título |         <Descricao> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do documento |         <NrDocumento> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome do Cliente/Fornecedor |         <ClienteFornecedor> | [1..1] | Alfanumerico | nan | nan |
| nan | CPF/CNPJ do Cliente/Fornecedor |         <CPFCNPJ> | [1..1] | Alfanumerico | nan | nan |
| nan | Tipo de documento |         <TipoDeDocumento> | [1..1] | Alfanumerico | nan | nan |
| nan | Centro de custo |         <CentroDeCusto> | [1..1] | Alfanumerico | nan | nan |
| nan | Valor do lançamento |         <VlrLancamento> | [1..1] | Numerico | nan | nan |
| nan | Descontos |         <VlrDescontos> | [1..1] | Numerico | nan | nan |
| nan | Acréscimos |         <VlrAcrescimos> | [1..1] | Numerico | nan | nan |
| nan | Juros de mora |         <VlrJurosMora> | [1..1] | Numerico | nan | nan |
| nan | Valor total |         <VlrTotal> | [1..1] | Numerico | nan | nan |
| nan | Valor pago/recebido |         <VlrPago> | [1..1] | Numerico | nan | nan |
| nan | Percentual dos acresc./desc. |         <PercAcresDesc> | [1..1] | Numerico | nan | nan |
| nan | Situação do título |         <CodSituacao> | [1..1] | Numerico | nan | nan |
| nan | Descrição da situação do título |         <DescSituacao> | [1..1] | Alfanumerico | nan | nan |
| nan | Categoria |         <Categoria> | [1..1] | Alfanumerico | nan | nan |
| nan | Observações |         <Observacoes> | [1..1] | Alfanumerico | nan | nan |
| nan | Fim grupo de dados títulos |     </Titulos> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC872

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Data de referência |       <DataRef> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Nr. Conta |       <NrConta> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC872> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC872Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados saldo conta bancaria |     <ContasBancarias> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Nr. Conta |         <NrConta> | [1..1] | Numerico | nan | nan |
| nan | Conta bancária |         <ContaBancaria> | [1..1] | Alfanumerico | nan | nan |
| nan | Saldo da conta |         <VlrSaldo> | [1..1] | Numerico | nan | nan |
| nan | Fim grupo de dados saldo conta bancaria |     </ContasBancarias> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC873

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Data de referência |       <DataRef> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Número centro de estoque |       <NrCentroEstoque> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Cód. Grupo de Produto |       <CodGrupoProduto> | [1..1] | Numerico | nan | nan |
| nan | Cód. Ref. Produto |       <CodRefProduto> | [1..1] | Alfanumerico | nan | nan |
| nan | Identificador para consolidar centro de estoque |       <ConsolidarCentroEstoque> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC873> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC873Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados posição de estoque |     <PosicaoEstoque> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Número centro de estoque |         <NrCentroEstoque> | [1..1] | Númerico | nan | nan |
| nan | Centro de estoque |         <CentroEstoque> | [1..1] | Alfanumerico | nan | nan |
| nan | Código do produto |         <CodProduto> | [1..1] | Numerico | nan | nan |
| nan | Código de barras |         <CodBarras> | [1..1] | AlfaNumerico | nan | nan |
| nan | Descrição do produto |         <DescProduto> | [1..1] | AlfaNumerico | nan | nan |
| nan | Cód. Grupo de produto |         <CodGrupo> | [1..1] | Numerico | nan | nan |
| nan | Grupo de produto |         <DescGrupo> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. unidade medida |         <Unidade> | [1..1] | AlfaNumerico | nan | nan |
| nan | Valor de venda do produto |         <VlrUnitario> | [1..1] | Numerico | nan | nan |
| nan | Saldo em estoque na data de referência |         <SaldoEstoque> | [1..1] | Numerico | nan | nan |
| nan | Custo médio unitário |         <VlrCustoMedioUnit> | [1..1] | Numerico | nan | nan |
| nan | Custo médio total |         <VlrCustoMedioTotal> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo de dados posição estoque |     </PosicaoEstoque> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC874

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Data início |       <DataInicio> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data fim |       <DataFim> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC874> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC874Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados avaliações |     <Avaliacoes> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Data |         <Data> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Mesa/Comanda |         <NrMesaComanda> | [1..1] | Numerico | nan | nan |
| nan | Nome do cliente |         <NomeCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | E-mail cliente |         <Email> | [1..1] | Alfanumerico | nan | nan |
| nan | Avaliação - Comida (de 1 a 5) |         <AvalicaoComida> | [1..1] | Numerico | nan | nan |
| nan | Avaliação - Bebida (de 1 a 5) |         <AvalicaoBebida> | [1..1] | Numerico | nan | nan |
| nan | Avaliação - Atendimento (de 1 a 5) |         <AvalicaoAtendimento> | [1..1] | Numerico | nan | nan |
| nan | Avaliação - Ambiente (de 1 a 5) |         <AvalicaoAmbiente> | [1..1] | Numerico | nan | nan |
| nan | Comentários |         <Comentarios> | [1..1] | Alfanumerico | nan | nan |
| nan | Fim grupo de dados avaliações |     </Avaliacoes> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC875

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Data início |       <DataInicio> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Data fim |       <DataFim> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC875> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC875Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados cadastro portaria |     <CadastroPortaria> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Mesa/Comanda |         <NrMesaComanda> | [1..1] | Numerico | nan | nan |
| nan | Código do cliente |         <NrCliente> | [1..1] | Numerico | nan | nan |
| nan | Nome do cliente |         <NomeCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | E-mail cliente |         <Email> | [1..1] | Alfanumerico | nan | nan |
| nan | Telefone cliente |         <Telefone> | [1..1] | Alfanumerico | nan | nan |
| nan | Sexo cliente (M - Masculino / F - Feminino) |         <Sexo> | [1..1] | Alfanumerico | nan | nan |
| nan | Data nascimento |         <DataNascimento> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | CPF/CNPJ |         <CpfCnpj> | [1..1] | Alfanumerico | nan | nan |
| nan | Data/hora de entrada |         <DaraHoraEntrada> | [1..1] | Datetime | DD-MM-AAAA HH:NN | nan |
| nan | Data/hora de saída |         <DaraHoraSaída> | [1..1] | Datetime | DD-MM-AAAA HH:NN | nan |
| nan | Valor compras |         <VlrCompras> | [1..1] | Numerico | nan | nan |
| nan | Fim grupo de dados cadastro portaria |     </CadastroPortaria> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC876

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Origem |       <Origem> | [1..1] | Numerico | nan | nan |
| nan | Nr. Pedido |       <NrPedido> | [1..1] | Numerico | nan | nan |
| nan | Nr. do sistema integrador |       <NrSistemaIntegrador> | [1..1] | [1..n] | Numerico | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC876> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC876Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados cadastro portaria |     <Pedidos> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Origem |         <Origem> | [1..1] | Numerico | nan | nan |
| nan | Nr. Pedido |         <NrPedido> | [1..1] | Numerico | nan | nan |
| nan | Numero de controle |         <NrCtrlPedido> | [1..1] | Numerico | nan | nan |
| nan | Tipo |         <Tipo> | [1..1] | Numerico | nan | nan |
| nan | Data do pedido |         <DataPedido> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Hora do pedido |         <HoraPedido> | [1..1] | Numerico | HHMM | nan |
| nan | Indicador de retirada no balcão)  |         <IndRetiradaBalcao> | [1..1] | Numerico | nan | nan |
| nan | Sistema gerador do pedido |         <NrSistemaIntegrador> | [1..1] | Numerico | nan | nan |
| nan | Pagamentos |         <Pagamentos> | [1..n] | Grupo | nan | nan |
| nan | Número da parcela |             <NrParcela> | [1..1] | Numerico | nan | nan |
| nan | Forma de pagamento |             <FormaPagamento> | [1..1] | Alfanumerico | nan | nan |
| nan | Valor pagamento |             <ValorPagamento> | [1..1] | Numerico | nan | nan |
| nan | Situação |             <SituacPag> | [1..1] | Numerico | nan | nan |
| nan | Pagamentos |         <Pagamentos> | [1..n] | Grupo | nan | nan |
| nan | Chave do pedido no sistema integrador |         <ChavePedidoSistemaIntegrador> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome do cliente |         <NomeCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | Telefone |         <FoneCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | Endereço |         <Endereco> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do endereço |         <NrEndereco> | [1..1] | Numerico | nan | nan |
| nan | Complemento do endereço |         <ComplementoEndereco> | [1..1] | Alfanumerico | nan | nan |
| nan | Bairro |         <Bairro> | [1..1] | Alfanumerico | nan | nan |
| nan | Cidade |         <Cidade> | [1..1] | Alfanumerico | nan | nan |
| nan | Estado |         <Estado> | [1..1] | Alfanumerico | nan | nan |
| nan | CEP |         <Cep> | [1..1] | Numerico | nan | nan |
| nan | Data prevista da entrega |         <DataPrevEntrega> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Hora prevista |         <HoraPrevEntrega> | [1..1] | Numerico | HHMM | nan |
| nan | Data da finalização |         <DataFinalizacao> | [1..1] | Numerico | AAAAMMDD | nan |
| nan | Hora da finalização |         <HoraFinalizacao> | [1..1] | Numerico | HHMM | nan |
| nan | Desconto fornecido por Vouchers (Ifood) |         <DescontoIntegracao> | [1..1] | Numerico | nan | nan |
| nan | Valor total de produtos |         <ValorTotalProdutos> | [1..1] | Numerico | nan | nan |
| nan | Taxa de entrega |         <ValorTaxaEntrega> | [1..1] | Numerico | nan | nan |
| nan | Custo operador logístico |         <CustoOperadorLogistico> | [1..1] | Numerico | nan | nan |
| nan | Status |         <Status> | [1..1] | Numerico | nan | nan |
| nan | Latitude do cliente |         <Latitude> | [1..1] | Numerico | nan | nan |
| nan | Longitude do cliente |         <Longitude> | [1..1] | Numerico | nan | nan |
| nan | Distancia em kilometros da loja |         <Distancia> | [1..1] | Numerico | nan | nan |
| nan | Historico |         <Historico> | [1..n] | Grupo | nan | nan |
| nan | Data do evento |             <Data> | [1..1] | Timestamp | nan | nan |
| nan | Descrição do evento |             <Descricao> | [1..1] | Alfanumerico | nan | nan |
| nan | Status do evento |             <StatusEvento> | [1..1] | Numerico | nan | nan |
| nan | Historico |         <Historico> | [1..n] | Grupo | nan | nan |
| nan | Número da filial que foi gerado o cupom |         <NrFilialCupom> | [1..1] | Numerico | nan | nan |
| nan | Filial que foi gerado o cupom |         <FilialCupom> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do caixa que foi gerado o cupom |         <NrCaixaCupom> | [1..1] | Numerico | nan | nan |
| nan | Caixa que foi gerado o cupom |         <CaixaCupom> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do cupom |         <NrCupom> | [1..1] | Numerico | nan | nan |
| nan | Fim grupo de dados cadastro portaria |     </Pedidos> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC877

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Origem |       <Origem> | [1..1] | Numerico | nan | nan |
| nan | Nr. Pedido |       <NrPedido> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC877> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC877Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Grupo dados cadastro portaria |     <Produtos> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Origem |         <Origem> | [1..1] | Numerico | nan | nan |
| nan | Nr. Pedido |         <NrPedido> | [1..1] | Numerico | nan | nan |
| nan | Nr. Item |         <NrItem> | [1..1] | Numerico | nan | nan |
| nan | Sequencial do item (para adicionais) |         <NrItemAdicional> | [1..1] | Numerico | nan | nan |
| nan | Cód de referencia |         <CodRef> | [1..1] | Alfanumerico | nan | nan |
| nan | Produto |         <Produto> | [1..1] | Alfanumerico | nan | nan |
| nan | Valor unitario do produto |         <ValorUnitProd> | [1..1] | Numerico | nan | nan |
| nan | Quantidade |         <Quantidade> | [1..1] | Numerico | nan | nan |
| nan | Unidade de medida |         <UnidadeDeMedida> | [1..1] | Alfanumerico | nan | nan |
| nan | Vl. Subtotal |         <ValorSubTotal> | [1..1] | Numerico | nan | nan |
| nan | Vl desconto |         <ValorDesconto> | [1..1] | Numerico | nan | nan |
| nan | Vl do acrescimo |         <ValorAcrescimo> | [1..1] | Numerico | nan | nan |
| nan | Vl total (subtot + acresc - desc) |         <ValorTotal> | [1..1] | Numerico | nan | nan |
| nan | Observaçoes |         <Observacoes> | [1..1] | Alfanumerico | nan | nan |
| nan | Fim grupo de dados cadastro portaria |     </Produtos> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC880

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Grupos de produto |       <CodGrupos> | [1..n] | Numerico[ ] | nan | nan |
| nan | Cód. Ref. Produtos |       <CodRefProdutos> | [1..n] | Alfanumerico[ ] | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC880> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC880Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Produtos |     <Produtos> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Produto |         <Produto> | [1..n] | Alfanumerico | nan | nan |
| nan | Código do produto |         <CodRefProduto> | [1..n] | Alfanumerico | nan | nan |
| nan | Código de barras |         <CodBarras> | [1..n] | AlfaNumerico | nan | nan |
| nan | Cód. Grupo de produto |         <CodGrupo> | [1..1] | Numerico | nan | nan |
| nan | Grupo de produto |         <DescGrupo> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. unidade medida |         <Unidade> | [1..1] | AlfaNumerico | nan | nan |
| nan | Valor de venda do produto |         <VlrUnitario> | [1..1] | Numerico | nan | nan |
| nan | Custo médio unitário |         <VlrCustoMedioUnit> | [1..1] | Numerico | nan | nan |
| nan | Custo total da receita |         <VlrCustoReceita> | [1..1] | Numerico | nan | nan |
| nan | Nr. Centro produtivo |         <NrCentroProdutivo> | [1..1] | Numerico | nan | nan |
| nan | Centro produtivo |         <CentroProdutivo> | [1..1] | AlfaNumerico | nan | nan |
| nan | Indicador de baixa dos insumos na venda |         <IndBaixaVenda> | [1..1] | Numerico | nan | nan |
| nan | Complexidade |         <ComplexReceita> | [1..1] | Numerico | nan | nan |
| nan | Peso total |         <PesoReceita> | [1..1] | Numerico | nan | nan |
| nan | Rendimento |         <Rendimento> | [1..1] | Numerico | nan | nan |
| nan | Unidade de medida secundária |         <UnidadeSec> | [1..1] | AlfaNumerico | nan | nan |
| nan | Rendimento secundário |         <RendimentoSec> | [1..1] | Numerico | nan | nan |
| nan | Instruções de preparo |         <InstrucoesPrep> | [1..1] | AlfaNumerico | nan | nan |
| nan | Insumos |         <Insumos> | [1..n] | Grupo | nan | nan |
| nan | Código do insumo |             <CodRefProduto> | [1..n] | AlfaNumerico | nan | nan |
| nan | Insumo |             <Insumo> | [1..1] | AlfaNumerico | nan | nan |
| nan | Cód. Grupo de produto |             <CodGrupo> | [1..1] | Numerico | nan | nan |
| nan | Grupo de produto |             <DescGrupo> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. unidade medida |             <Unidade> | [1..1] | AlfaNumerico | nan | nan |
| nan | Custo médio unitário |             <VlrCustoMedioUnit> | [1..1] | Numerico | nan | nan |
| nan | Custo total |             <VlrCustoTotal> | [1..1] | Numerico | nan | nan |
| nan | Indicador de insumo principal |             <IndInsumoPrinc> | [1..1] | Numerico | nan | nan |
| nan | Tipo |             <Tipo> | [1..1] | Numerico | nan | nan |
| nan | Quantidade |             <Quantidade> | [1..1] | Numerico | nan | nan |
| nan | Sub insumos |             <SubInsumos> | [1..n] | Grupo | nan | nan |
| nan | Código do insumo |                 <CodRefProduto> | [1..n] | AlfaNumerico | nan | nan |
| nan | Insumo |                 <Insumo> | [1..1] | AlfaNumerico | nan | nan |
| nan | Cód. Grupo de produto |                 <CodGrupo> | [1..1] | Numerico | nan | nan |
| nan | Grupo de produto |                 <DescGrupo> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. unidade medida |                 <Unidade> | [1..1] | AlfaNumerico | nan | nan |
| nan | Tipo |                 <Tipo> | [1..1] | Numerico | nan | nan |
| nan | Quantidade |                 <Quantidade> | [1..1] | Numerico | nan | nan |
| nan | Sub insumos |             </SubInsumos> | [1..n] | Grupo | nan | nan |
| nan | Insumos |         </Insumos> | [1..n] | Grupo | nan | nan |
| nan | Produtos |     </Produtos> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC881

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Data inicial |       <DataIni> | [1..1] | Numerico | YYYYMMDD | nan |
| nan | Data final |       <DataFim> | [1..1] | Numerico | YYYYMMDD | nan |
| nan | Grupos de produto |       <CodGrupos> | [1..n] | Numerico[ ] | nan | nan |
| nan | Cód. Ref. Produtos |       <CodRefProdutos> | [1..n] | Alfanumerico[ ] | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC881> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC881Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Produtos |     <Produtos> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | Produto |         <Produto> | [1..n] | Alfanumerico | nan | nan |
| nan | Código do produto |         <CodRefProduto> | [1..n] | Alfanumerico | nan | nan |
| nan | Código de barras |         <CodBarras> | [1..n] | AlfaNumerico | nan | nan |
| nan | Cód. Grupo de produto |         <CodGrupo> | [1..1] | Numerico | nan | nan |
| nan | Grupo de produto |         <DescGrupo> | [1..1] | Alfanumerico | nan | nan |
| nan | Cód. unidade medida |         <Unidade> | [1..1] | AlfaNumerico | nan | nan |
| nan | Valor de venda do produto |         <VlrUnitario> | [1..1] | Numerico | nan | nan |
| nan | Histórico de preços |         <HistoricoPreco> | [1..n] | Grupo | nan | nan |
| nan | Data |             <Data> | [1..1] | Numerico | YYYYMMDD | nan |
| nan | Filial |             <Filial> | [1..1] | AlfaNumerico | nan | nan |
| nan | Novo preço a vista |             <NovoPrecoVista> | [1..1] | Numerico | nan | nan |
| nan | Novo preço a prazo |             <NovoPrecoPrazo> | [1..1] | Numerico | nan | nan |
| nan | Usuário responsável |             <Usuario> | [1..1] | AlfaNumerico | nan | nan |
| nan | Histórico de preços |         </HistoricoPreco> | [1..n] | Grupo | nan | nan |
| nan | Produtos |     </Produtos> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC882

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC882> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC882Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Filiais |     <Filiais> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da filial |         <NrFilial> | [1..1] | Númerico | nan | nan |
| nan | Filial |         <Filial> | [1..1] | Alfanumerico | nan | nan |
| nan | CNPJ da filial |         <CNPJFilial> | [1..1] | Alfanumerico | nan | nan |
| nan | Filiais |     </Filiais> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC883

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | Data inicial |     <DataIni> | [1..1] | Numerico | nan | nan |
| nan | Data final |     <DataFim> | [1..1] | Numerico | nan | nan |
| nan | Filtro de previsto/realizado |     <IndPrevReal> | [1..1] | Numerico | nan | nan |
| nan | Regime |     <IndRegime> | [1..1] | Numerico | nan | nan |
| nan | Tipo de relatório |     <IndTipoRel> | [1..1] | Numerico | nan | nan |
| nan | Contas bancárias |     <NrContas> | [1..n] | Numerico[ ] | nan | nan |
| nan | Centros de custo |     <NrCentrosCusto> | [1..n] | Numerico[ ] | nan | nan |
| nan | Filiais |     <NrFiliais> | [1..n] | Numerico[ ] | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC883> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC883Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Categorias |     <Categorias> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da categoria |         <NrCategoria> | [1..1] | Númerico | nan | nan |
| nan | Descrição da categoria |         <Categoria> | [1..1] | Alfanumerico | nan | nan |
| nan | Tipo de categoria |         <TipoCategoria> | [1..1] | Alfanumerico | nan | nan |
| nan | Número da categoria superior |         <NrCategoriaSup> | [1..1] | Númerico | nan | nan |
| nan | Nível |         <Nivel> | [1..1] | Númerico | nan | nan |
| nan | Período |         <Periodo> | [1..1] | Alfanumerico | nan | nan |
| nan | Valor |         <Valor> | [1..1] | Númerico | nan | nan |
| nan | Percentual |         <Percentual> | [1..1] | Númerico | nan | nan |
| nan | Categorias |     </Categorias> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |

## CC884

| Descrição do campo | Nome do campo | Ocorrência | Tipo | Tam. | Valor fixo | Obrigatório |
|---------------------|----------------|------------|------|------|-------------|--------------|
| nan | Grupo solicitante |   <Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Login usuário resp. manutenção |     <LoginUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Nome usuário resp. manutenção |     <NomeUsr> | [1..1] | Alfanumerico | nan | nan |
| nan | Código da empresa |     <CodEmpresa> | [1..1] | Numerico | nan | nan |
| nan | Código da filial |     <CodFilial> | [1..1] | Numerico | nan | nan |
| nan | Fim do grupo solicitante |   </Solicitante> | [1..1] | Grupo | nan | nan |
| nan | Grupo filtros |   <Filtros> | [1..1] | Grupo | nan | nan |
| nan | CPF/CNPJ |     <CpfCnpj> | [1..1] | Alfanumerico | nan | nan |
| nan | Tipo de pessoa (PF/PJ) |     <TpPessoa> | [1..1] | Alfanumerico | nan | nan |
| nan | Tipo de clientes |     <TpClientes> | [1..n] | Alfanumerico[ ] | nan | nan |
| nan | Fim do grupo filtros |   </Filtros> | [1..1] | Grupo | nan | nan |
| nan | Fim grupo principal do arquivo | </CFYCC884> | [1..1] | Grupo | nan | nan |
| nan | Resultado | nan | nan | nan | nan | nan |
| nan | Grupo principal do arquivo | <CFYCC884Result> | [1..1] | Grupo | nan | nan |
| nan | Grupo resultado |   <Resultado> | [1..1] | Grupo | nan | nan |
| nan | Código retorno (0 - Sucesso; Diferente de 0 - Erro) |     <CodRet> | [1..1] | Numerico | nan | nan |
| nan | Mensagem de retorno |     <MensagemRet> | [1..1] | Alfanumerico | nan | nan |
| nan | Categorias |     <Enderecos> | [1..n] | Grupo | nan | nan |
| nan | Número da empresa |         <NrEmpresa> | [1..1] | Númerico | nan | nan |
| nan | Empresa |         <Empresa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do cliente |         <NrCliente> | [1..1] | Númerico | nan | nan |
| nan | Cliente |         <Cliente> | [1..1] | Alfanumerico | nan | nan |
| nan | Sequencial de endereço |         <SeqEndereco> | [1..1] | Númerico | nan | nan |
| nan | CPF/CNPJ |         <CpfCnpj> | [1..1] | Alfanumerico | nan | nan |
| nan | Tipo de pessoa (PF/PJ) |         <TpPessoa> | [1..1] | Alfanumerico | nan | nan |
| nan | Número do tipo de cliente |         <NrTpCliente> | [1..1] | Numérico | nan | nan |
| nan | Tipo de cliente |         <TpCliente> | [1..1] | Alfanumerico | nan | nan |
| nan | Data de nascimento |         <DataNascimento> | [1..1] | Numérico | YYYYMMDD | nan |
| nan | Email |         <Email> | [1..1] | Alfanumerico | nan | nan |
| nan | Telefone |         <Telefone> | [1..1] | Numérico | nan | nan |
| nan | Celular |         <Celular> | [1..1] | Numérico | nan | nan |
| nan | Número do tipo de endereço |         <NrTpEndereco> | [1..1] | Numérico | nan | nan |
| nan | Tipo de endereço |         <TpEndereco> | [1..1] | Alfanumerico | nan | nan |
| nan | Logradouro |         <Logradouro> | [1..1] | Alfanumerico | nan | nan |
| nan | Número |         <Numero> | [1..1] | Numérico | nan | nan |
| nan | Complemento |         <Complemento> | [1..1] | Alfanumerico | nan | nan |
| nan | Bairro |         <Bairro> | [1..1] | Alfanumerico | nan | nan |
| nan | Cidade |         <Cidade> | [1..1] | Alfanumerico | nan | nan |
| nan | CEP |         <Cep> | [1..1] | Numérico | nan | nan |
| nan | Estado (Sigla) |         <Estado> | [1..1] | Alfanumerico | nan | nan |
| nan | Categorias |     </Enderecos> | [1..n] | Grupo | nan | nan |
| nan | Fim grupo resultado |   </Resultado> | [1..1] | Grupo | nan | nan |
